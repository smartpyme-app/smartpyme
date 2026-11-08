<?php

namespace App\Services\Contabilidad;

use App\Models\Contabilidad\Activo;
use App\Models\Contabilidad\ActivoCategoria;
use App\Models\Contabilidad\ActivoConfiguracion;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ActivoImportacionMasivaService
{
    public function __construct(
        private DepreciacionService $depreciacionService,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $filas
     * @return array{creados: int, omitidos: int, errores: array<int, array{fila: int, mensaje: string}>}
     */
    public function procesar(array $filas, int $idEmpresa, int $idUsuario): array
    {
        $resultado = ['creados' => 0, 'omitidos' => 0, 'errores' => []];
        $referenciasEnArchivo = [];
        $categoriasPorNombre = ActivoCategoria::withoutGlobalScopes()
            ->where('id_empresa', $idEmpresa)
            ->get()
            ->keyBy(fn (ActivoCategoria $c) => mb_strtolower(trim($c->nombre)));

        foreach ($filas as $indice => $fila) {
            $numeroFila = $indice + 2;

            if ($this->filaVacia($fila)) {
                continue;
            }

            try {
                $referencia = $this->textoObligatorio($fila, 'referencia', $numeroFila);
                $refKey = mb_strtolower($referencia);

                if (isset($referenciasEnArchivo[$refKey])) {
                    throw new RuntimeException("La referencia «{$referencia}» está duplicada en el archivo (fila {$referenciasEnArchivo[$refKey]}).");
                }
                $referenciasEnArchivo[$refKey] = $numeroFila;

                $payload = $this->mapearFila($fila, $numeroFila, $categoriasPorNombre);

                $existente = Activo::withoutGlobalScopes()
                    ->where('id_empresa', $idEmpresa)
                    ->whereRaw('LOWER(TRIM(referencia)) = ?', [$refKey])
                    ->first();

                if ($existente) {
                    if ($this->filaCoincideConActivo($existente, $payload)) {
                        $resultado['omitidos']++;

                        continue;
                    }

                    throw new RuntimeException("Ya existe un activo con referencia «{$referencia}» con datos distintos.");
                }

                DB::transaction(function () use ($payload, $idEmpresa, $idUsuario, &$resultado) {
                    $activo = new Activo;
                    $activo->fill($payload);
                    $activo->id_empresa = $idEmpresa;
                    $activo->id_usuario = $idUsuario;
                    $activo->estado_registro = 'activo';
                    $activo->estado = $activo->estado ?: 'En uso';

                    if (! $activo->fecha_inicio_depreciacion) {
                        $activo->fecha_inicio_depreciacion = $activo->fecha_compra;
                    }

                    $dec = (int) (ActivoConfiguracion::forEmpresa($idEmpresa)->redondeo_decimales ?? 2);
                    $base = $this->depreciacionService->ajustarPorBienUsado($activo);
                    $residual = (float) ($activo->valor_residual ?? 0);
                    $activo->valor_en_libros = max($residual, round($base - (float) $activo->depreciacion_acumulada, $dec));

                    $activo->save();
                    $activo->load('categoria');

                    if ($activo->estado_registro === 'activo') {
                        $this->depreciacionService->generarCronograma($activo);
                    }

                    $resultado['creados']++;
                });
            } catch (RuntimeException $e) {
                $resultado['errores'][] = ['fila' => $numeroFila, 'mensaje' => $e->getMessage()];
            }
        }

        return $resultado;
    }

    /** @param  array<string, mixed>  $fila */
    private function filaVacia(array $fila): bool
    {
        foreach ($fila as $valor) {
            if ($valor !== null && trim((string) $valor) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $fila
     * @param  \Illuminate\Support\Collection<string, ActivoCategoria>  $categoriasPorNombre
     * @return array<string, mixed>
     */
    private function mapearFila(array $fila, int $numeroFila, $categoriasPorNombre): array
    {
        $nombre = $this->textoObligatorio($fila, 'nombre', $numeroFila);
        $nombreCategoria = $this->textoObligatorio($fila, 'nombre_categoria', $numeroFila);
        $categoria = $categoriasPorNombre->get(mb_strtolower(trim($nombreCategoria)));

        if (! $categoria) {
            throw new RuntimeException("Categoría «{$nombreCategoria}» no existe en la empresa.");
        }

        $fechaCompra = $this->parseFecha($fila, 'fecha_compra', $numeroFila, true);
        $valorCompra = $this->numeroObligatorio($fila, 'valor_compra', $numeroFila);

        $depInicial = $this->numeroOpcional($fila, 'depreciacion_acumulada_inicial') ?? 0.0;
        if ($depInicial < 0) {
            throw new RuntimeException('depreciacion_acumulada_inicial no puede ser negativa.');
        }

        $esUsado = $this->booleano($fila, 'es_usado');
        $porcentajeUsado = $this->numeroOpcional($fila, 'porcentaje_base_usado');
        if ($esUsado && ($porcentajeUsado === null || $porcentajeUsado <= 0)) {
            $porcentajeUsado = 100.0;
        }

        return [
            'referencia' => $this->textoObligatorio($fila, 'referencia', $numeroFila),
            'nombre' => $nombre,
            'id_categoria' => $categoria->id,
            'fecha_compra' => $fechaCompra,
            'valor_compra' => $valorCompra,
            'vida_util' => $this->numeroOpcional($fila, 'vida_util'),
            'valor_residual' => $this->numeroOpcional($fila, 'valor_residual') ?? 0.0,
            'fecha_inicio_depreciacion' => $this->parseFecha($fila, 'fecha_inicio_depreciacion', $numeroFila, false) ?? $fechaCompra,
            'depreciacion_acumulada' => $depInicial,
            'numero_de_serie' => $this->textoOpcional($fila, 'numero_de_serie'),
            'ubicacion' => $this->textoOpcional($fila, 'ubicacion'),
            'descripcion' => $this->textoOpcional($fila, 'descripcion'),
            'es_usado' => $esUsado,
            'porcentaje_base_usado' => $esUsado ? $porcentajeUsado : null,
        ];
    }

    /** @param  array<string, mixed>  $payload */
    private function filaCoincideConActivo(Activo $activo, array $payload): bool
    {
        return (int) $activo->id_categoria === (int) $payload['id_categoria']
            && trim((string) $activo->nombre) === trim((string) $payload['nombre'])
            && abs((float) $activo->valor_compra - (float) $payload['valor_compra']) < 0.01
            && $activo->fecha_compra->format('Y-m-d') === Carbon::parse($payload['fecha_compra'])->format('Y-m-d')
            && abs((float) $activo->depreciacion_acumulada - (float) $payload['depreciacion_acumulada']) < 0.01;
    }

    /** @param  array<string, mixed>  $fila */
    private function textoObligatorio(array $fila, string $clave, int $filaNum): string
    {
        $valor = trim((string) ($fila[$clave] ?? ''));
        if ($valor === '') {
            throw new RuntimeException("El campo «{$clave}» es obligatorio.");
        }

        return $valor;
    }

    /** @param  array<string, mixed>  $fila */
    private function textoOpcional(array $fila, string $clave): ?string
    {
        $valor = trim((string) ($fila[$clave] ?? ''));

        return $valor === '' ? null : $valor;
    }

    /** @param  array<string, mixed>  $fila */
    private function numeroObligatorio(array $fila, string $clave, int $filaNum): float
    {
        $raw = $fila[$clave] ?? null;
        if ($raw === null || $raw === '') {
            throw new RuntimeException("El campo «{$clave}» es obligatorio.");
        }
        if (! is_numeric($raw)) {
            throw new RuntimeException("«{$clave}» debe ser numérico.");
        }

        return (float) $raw;
    }

    /** @param  array<string, mixed>  $fila */
    private function numeroOpcional(array $fila, string $clave): ?float
    {
        $raw = $fila[$clave] ?? null;
        if ($raw === null || $raw === '') {
            return null;
        }
        if (! is_numeric($raw)) {
            throw new RuntimeException("«{$clave}» debe ser numérico.");
        }

        return (float) $raw;
    }

    /** @param  array<string, mixed>  $fila */
    private function parseFecha(array $fila, string $clave, int $filaNum, bool $obligatoria): ?string
    {
        $raw = $fila[$clave] ?? null;
        if ($raw === null || trim((string) $raw) === '') {
            if ($obligatoria) {
                throw new RuntimeException("El campo «{$clave}» es obligatorio.");
            }

            return null;
        }

        try {
            if (is_numeric($raw)) {
                return Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $raw))->format('Y-m-d');
            }

            return Carbon::parse((string) $raw)->format('Y-m-d');
        } catch (\Throwable) {
            throw new RuntimeException("«{$clave}» no es una fecha válida.");
        }
    }

    /** @param  array<string, mixed>  $fila */
    private function booleano(array $fila, string $clave): bool
    {
        $raw = strtolower(trim((string) ($fila[$clave] ?? '')));
        if ($raw === '' || in_array($raw, ['0', 'no', 'false'], true)) {
            return false;
        }

        return in_array($raw, ['1', 'si', 'sí', 'yes', 'true'], true);
    }
}

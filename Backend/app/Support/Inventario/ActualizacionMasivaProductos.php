<?php

namespace App\Support\Inventario;

use App\Models\Inventario\Bodega;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Producto;
use App\Services\ImpuestosService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ActualizacionMasivaProductos
{
    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>|null  $producto
     * @param  array<int, array{stock?: float, stock_minimo?: float, stock_maximo?: float}>  $inventarios
     * @param  array<int, int>  $bodegasActivas
     * @param  array<int, string>  $codigosPorId
     * @return array{ok: bool, error?: string, producto?: array<string, mixed>, inventarios?: array<int, array<string, float>>, registrar_kardex?: bool}
     */
    public function evaluarFila(
        array $row,
        ?array $producto,
        array $inventarios,
        array $bodegasActivas,
        array $codigosPorId,
        float $porcentajeIva
    ): array {
        $id = $this->idFila($row);
        if ($id === null) {
            return ['ok' => false, 'error' => 'El ID de creación es obligatorio.'];
        }
        if ($producto === null || (int) ($producto['id'] ?? 0) !== $id) {
            return ['ok' => false, 'error' => 'El ID de creación no existe.'];
        }
        if (($producto['tipo'] ?? '') === 'Servicio') {
            return ['ok' => false, 'error' => 'El ID no corresponde a un producto de inventario.'];
        }

        $patch = [];
        $invPatch = [];
        $kardex = false;
        $bodegas = array_fill_keys(array_map('intval', $bodegasActivas), true);

        if (array_key_exists('codigo', $row) && $this->presente($row['codigo'])) {
            $codigo = trim((string) $row['codigo']);
            $clave = mb_strtolower($codigo);
            foreach ($codigosPorId as $otroId => $otroCodigo) {
                if ((int) $otroId !== $id && mb_strtolower(trim((string) $otroCodigo)) === $clave) {
                    return ['ok' => false, 'error' => 'El código ya pertenece a otro producto.'];
                }
            }
            if ($codigo !== (string) ($producto['codigo'] ?? '')) {
                $patch['codigo'] = $codigo;
            }
        }

        $costo = $this->numero($row['costo'] ?? null, 'El costo');
        if (isset($costo['error'])) {
            return ['ok' => false, 'error' => $costo['error']];
        }
        if (isset($costo['value'])) {
            if (!$this->igual($costo['value'], $producto['costo'] ?? null)) {
                $patch['costo'] = $costo['value'];
                $kardex = true;
            }
            if (!$this->igual($costo['value'], $producto['costo_promedio'] ?? null)) {
                $patch['costo_promedio'] = $costo['value'];
            }
        }

        $precio = $this->numero($row['precio_sin_iva'] ?? null, 'El precio sin IVA');
        if (isset($precio['error'])) {
            return ['ok' => false, 'error' => $precio['error']];
        }
        if (isset($precio['value'])) {
            $sin = $precio['value'];
            $con = $porcentajeIva > 0 ? round($sin * (1 + ($porcentajeIva / 100)), 2) : $sin;
            if (!$this->igual($sin, $producto['precio'] ?? null)) {
                $patch['precio'] = $sin;
                $kardex = true;
            }
            if (!$this->igual($sin, $producto['precio_sin_iva'] ?? null)) {
                $patch['precio_sin_iva'] = $sin;
            }
            if (!$this->igual($con, $producto['precio_con_iva'] ?? null)) {
                $patch['precio_con_iva'] = $con;
            }
        }

        foreach (['entra_a_menu' => 'mostrar_en_restaurante', 'mostrar_en_restaurante' => 'mostrar_en_restaurante', 'genera_comanda' => 'genera_comanda'] as $columna => $campo) {
            if (!array_key_exists($columna, $row)) {
                continue;
            }
            $bandera = $this->bandera($row[$columna], $columna === 'genera_comanda' ? 'genera comanda' : 'entra a menú');
            if (isset($bandera['error'])) {
                return ['ok' => false, 'error' => $bandera['error']];
            }
            if (!isset($bandera['value'])) {
                continue;
            }
            $actual = (bool) ($producto[$campo] ?? false);
            if ($bandera['value'] !== $actual) {
                $patch[$campo] = $bandera['value'];
            }
        }

        foreach ($row as $key => $value) {
            $columna = $this->parseColumnaBodega((string) $key);
            if ($columna === null) {
                continue;
            }
            $numero = $this->numero($value, $columna['campo'] === 'stock_minimo' ? 'El stock mínimo' : 'El stock máximo');
            if (isset($numero['error'])) {
                return ['ok' => false, 'error' => $numero['error']];
            }
            if (!isset($numero['value'])) {
                continue;
            }
            $idBodega = $columna['id_bodega'];
            if (!isset($bodegas[$idBodega])) {
                return ['ok' => false, 'error' => 'La bodega '.$idBodega.' no es una bodega activa de la empresa.'];
            }
            if (!isset($inventarios[$idBodega])) {
                return ['ok' => false, 'error' => 'El producto no tiene inventario en la bodega '.$idBodega.'.'];
            }
            $actual = $inventarios[$idBodega][$columna['campo']] ?? null;
            if (!$this->igual($numero['value'], $actual)) {
                $invPatch[$idBodega][$columna['campo']] = $numero['value'];
            }
        }

        return [
            'ok' => true,
            'producto' => $patch,
            'inventarios' => $invPatch,
            'registrar_kardex' => $kardex,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $filas
     * @return array{actualizados: int, errores: array<int, array{fila: int, mensaje: string}>}
     */
    public function procesar(array $filas, int $idEmpresa, int $idUsuario): array
    {
        $ids = [];
        foreach ($filas as $fila) {
            $id = $this->idFila($fila);
            if ($id !== null) {
                $ids[] = $id;
            }
        }
        $ids = array_values(array_unique($ids));

        $productos = Producto::withoutGlobalScope('empresa')
            ->where('id_empresa', $idEmpresa)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $bodegas = Bodega::withoutGlobalScope('empresa')
            ->where('id_empresa', $idEmpresa)
            ->where('activo', true)
            ->orderBy('id_sucursal')
            ->orderBy('id')
            ->get();
        $bodegaIds = $bodegas->pluck('id')->map(fn ($id) => (int) $id)->all();

        $inventarios = Inventario::query()
            ->whereIn('id_producto', $ids)
            ->whereIn('id_bodega', $bodegaIds ?: [0])
            ->get()
            ->groupBy('id_producto');

        $codigosPorId = Producto::withoutGlobalScope('empresa')
            ->where('id_empresa', $idEmpresa)
            ->whereNotNull('codigo')
            ->where('codigo', '!=', '')
            ->pluck('codigo', 'id')
            ->all();

        $ivaEmpresa = (float) app(ImpuestosService::class)->obtenerPorcentajeImpuesto($idEmpresa);
        $actualizados = 0;
        $errores = [];
        $vistos = [];

        foreach ($filas as $indice => $fila) {
            if ($this->filaVacia($fila)) {
                continue;
            }
            $numeroFila = $indice + 2;
            $id = $this->idFila($fila);
            if ($id !== null && isset($vistos[$id])) {
                $errores[] = ['fila' => $numeroFila, 'mensaje' => 'El ID de creación está repetido en el archivo.'];
                continue;
            }
            if ($id !== null) {
                $vistos[$id] = true;
            }

            $modelo = $id !== null ? $productos->get($id) : null;
            $invModelo = $modelo ? $inventarios->get($modelo->id, collect()) : collect();
            $inv = [];
            foreach ($invModelo as $item) {
                $inv[(int) $item->id_bodega] = [
                    'stock' => (float) $item->stock,
                    'stock_minimo' => (float) $item->stock_minimo,
                    'stock_maximo' => (float) $item->stock_maximo,
                ];
            }

            $pct = $modelo && (float) ($modelo->porcentaje_impuesto ?? 0) > 0
                ? (float) $modelo->porcentaje_impuesto
                : $ivaEmpresa;

            $resultado = $this->evaluarFila(
                $fila,
                $modelo ? [
                    'id' => (int) $modelo->id,
                    'tipo' => $modelo->tipo,
                    'codigo' => $modelo->codigo,
                    'costo' => $modelo->costo,
                    'costo_promedio' => $modelo->costo_promedio,
                    'precio' => $modelo->precio,
                    'precio_sin_iva' => $modelo->precio_sin_iva,
                    'precio_con_iva' => $modelo->precio_con_iva,
                    'mostrar_en_restaurante' => (bool) $modelo->mostrar_en_restaurante,
                    'genera_comanda' => (bool) $modelo->genera_comanda,
                ] : null,
                $inv,
                $bodegaIds,
                $codigosPorId,
                $pct
            );

            if (!$resultado['ok']) {
                $errores[] = ['fila' => $numeroFila, 'mensaje' => $resultado['error']];
                continue;
            }

            if ($resultado['producto'] === [] && $resultado['inventarios'] === []) {
                $actualizados++;
                continue;
            }

            try {
                DB::transaction(function () use ($modelo, $resultado, $idUsuario) {
                    if ($resultado['producto'] !== []) {
                        $modelo->fill($resultado['producto']);
                        $modelo->save();
                    }
                    foreach ($resultado['inventarios'] as $idBodega => $valores) {
                        $inventario = Inventario::query()
                            ->where('id_producto', $modelo->id)
                            ->where('id_bodega', $idBodega)
                            ->first();
                        if (!$inventario) {
                            throw new \RuntimeException('El producto no tiene inventario en la bodega '.$idBodega.'.');
                        }
                        if (array_key_exists('stock_minimo', $valores)) {
                            $inventario->stock_minimo = $valores['stock_minimo'];
                        }
                        if (array_key_exists('stock_maximo', $valores)) {
                            $inventario->stock_maximo = $valores['stock_maximo'];
                        }
                        $inventario->save();
                    }
                    if ($resultado['registrar_kardex']) {
                        $modelo->refresh();
                        foreach (Inventario::where('id_producto', $modelo->id)->get() as $inventario) {
                            if ((float) $inventario->stock > 0) {
                                $inventario->kardex($modelo, 0, $modelo->precio, $modelo->costo, null, [
                                    'id_usuario' => $idUsuario,
                                ]);
                            }
                        }
                    }
                });
                if (isset($resultado['producto']['codigo'])) {
                    $codigosPorId[$modelo->id] = $resultado['producto']['codigo'];
                }
                $actualizados++;
            } catch (\Throwable $e) {
                Log::error('Actualización masiva de productos: '.$e->getMessage(), ['fila' => $numeroFila]);
                $errores[] = ['fila' => $numeroFila, 'mensaje' => 'No se pudo actualizar el producto.'];
            }
        }

        return ['actualizados' => $actualizados, 'errores' => $errores];
    }

    public static function encabezadoStock(string $campo, int $idBodega, string $nombre, string $sucursal): string
    {
        $prefijo = $campo === 'stock_maximo' ? 'stock_maximo' : 'stock_minimo';

        return $prefijo.'_'.$idBodega.' - '.self::sanear($nombre).' ('.self::sanear($sucursal).')';
    }

    /**
     * @return array{campo: string, id_bodega: int}|null
     */
    public function parseColumnaBodega(string $key): ?array
    {
        if (!preg_match('/^stock_(minimo|maximo)_(\d+)/', $key, $m)) {
            return null;
        }

        return [
            'campo' => $m[1] === 'minimo' ? 'stock_minimo' : 'stock_maximo',
            'id_bodega' => (int) $m[2],
        ];
    }

    private function idFila(array $row): ?int
    {
        foreach (['id_creacion', 'id_de_creacion', 'id'] as $clave) {
            if (!array_key_exists($clave, $row) || !$this->presente($row[$clave])) {
                continue;
            }
            $raw = $row[$clave];
            if (!is_numeric($raw) || (float) $raw != (int) $raw) {
                return null;
            }

            return (int) $raw;
        }

        return null;
    }

    private function filaVacia(array $fila): bool
    {
        foreach ($fila as $valor) {
            if ($this->presente($valor)) {
                return false;
            }
        }

        return true;
    }

    private function presente($valor): bool
    {
        return $valor !== null && trim((string) $valor) !== '';
    }

    /**
     * @return array{value?: float, error?: string}
     */
    private function numero($valor, string $etiqueta): array
    {
        if (!$this->presente($valor)) {
            return [];
        }
        if (!is_numeric($valor)) {
            return ['error' => $etiqueta.' no es un número válido.'];
        }
        $numero = round((float) $valor, 4);
        if ($numero < 0) {
            return ['error' => $etiqueta.' no puede ser negativo.'];
        }

        return ['value' => $numero];
    }

    /**
     * @return array{value?: bool, error?: string}
     */
    private function bandera($valor, string $etiqueta): array
    {
        if (!$this->presente($valor)) {
            return [];
        }
        if (is_bool($valor)) {
            return ['value' => $valor];
        }
        if (is_numeric($valor)) {
            return ['value' => (float) $valor != 0.0];
        }
        $texto = mb_strtolower(trim((string) $valor));
        $texto = str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], $texto);
        if (in_array($texto, ['1', 'si', 'true', 'yes', 'y', 'x', 'verdadero'], true)) {
            return ['value' => true];
        }
        if (in_array($texto, ['0', 'no', 'false', 'n', 'falso'], true)) {
            return ['value' => false];
        }

        return ['error' => 'El valor de '.$etiqueta.' no es válido. Use Si o No.'];
    }

    private function igual($a, $b): bool
    {
        if ($a === null || $b === null || $a === '' || $b === '') {
            return false;
        }

        return abs((float) $a - (float) $b) < 0.0001;
    }

    private static function sanear(string $texto): string
    {
        $texto = str_replace(["\r", "\n"], ' ', $texto);
        $texto = preg_replace('/\s+/', ' ', trim($texto));

        return $texto !== '' ? $texto : '-';
    }
}

<?php

namespace App\Support\Inventario;

use App\Models\Inventario\Inventario;
use App\Models\Inventario\InventarioUbicacion;
use Illuminate\Support\Facades\DB;

class ImportacionUbicaciones
{
    /**
     * Agrega o actualiza ubicaciones. No modifica stock ni datos del producto.
     *
     * @param  array<int, array<string, mixed>>  $filas
     * @return array{agregados: int, actualizados: int, errores: array<int, array{fila: int, mensaje: string}>}
     */
    public function procesar(array $filas, int $idEmpresa): array
    {
        $agregados = 0;
        $actualizados = 0;
        $errores = [];
        $indiceProductos = $this->indice($idEmpresa);

        foreach ($filas as $indice => $fila) {
            $numero = $indice + 2;
            if ($this->filaVacia($fila)) {
                continue;
            }

            $idUbicacion = $this->entero($fila['id_ubicacion'] ?? null);
            if (!$idUbicacion && !$this->tieneLugar($fila)) {
                continue;
            }
            if ($idUbicacion) {
                $error = $this->actualizar($idUbicacion, $fila, $idEmpresa);
                if ($error) {
                    $errores[] = ['fila' => $numero, 'mensaje' => $error];
                } else {
                    $actualizados++;
                }
                continue;
            }

            $resultado = $this->agregar($fila, $indiceProductos);
            if ($resultado['error']) {
                $errores[] = ['fila' => $numero, 'mensaje' => $resultado['error']];
            } elseif ($resultado['creado']) {
                $agregados++;
            }
        }

        return [
            'agregados' => $agregados,
            'actualizados' => $actualizados,
            'errores' => $errores,
        ];
    }

    private function actualizar(int $idUbicacion, array $fila, int $idEmpresa): ?string
    {
        $ubicacion = InventarioUbicacion::query()
            ->where('id', $idUbicacion)
            ->whereHas('inventario.producto', function ($q) use ($idEmpresa) {
                $q->where('id_empresa', $idEmpresa);
            })
            ->first();

        if (!$ubicacion) {
            return 'La ubicación no existe en esta empresa.';
        }

        $cambios = [];
        foreach (['ubicacion', 'ubicacion_especifica'] as $campo) {
            if (!$this->presente($fila[$campo] ?? null)) {
                continue;
            }
            $valor = $this->texto($fila[$campo]);
            if ($valor !== null && mb_strlen($valor) > 100) {
                return 'El campo ' . $campo . ' admite máximo 100 caracteres.';
            }
            $cambios[$campo] = $valor;
        }
        if ($this->presente($fila['detalle'] ?? null)) {
            $cambios['detalle'] = $this->texto($fila['detalle']);
        }
        $activa = $this->activa($fila['sin_existencias'] ?? null);
        if ($activa !== null) {
            $cambios['activa'] = $activa;
        }

        if ($cambios !== []) {
            $ubicacion->fill($cambios);
            $ubicacion->save();
        }

        return null;
    }

    /** @return array{error: ?string, creado: bool} */
    private function agregar(array $fila, array $indice): array
    {
        $codigo = $this->texto($fila['codigo'] ?? null);
        $nombre = $this->texto($fila['nombre'] ?? null);
        $bodegaNombre = $this->texto($fila['bodega'] ?? null);
        if ($codigo === null && $nombre === null) {
            return ['error' => 'Indique el nombre del producto.', 'creado' => false];
        }
        if ($bodegaNombre === null) {
            return ['error' => 'La bodega es obligatoria para agregar una ubicación.', 'creado' => false];
        }

        $ubicacion = $this->texto($fila['ubicacion'] ?? null);
        $especifica = $this->texto($fila['ubicacion_especifica'] ?? null);
        $detalle = $this->texto($fila['detalle'] ?? null);
        if ($ubicacion === null && $especifica === null && $detalle === null) {
            return ['error' => 'Indique al menos ubicación, ubicación específica o detalle.', 'creado' => false];
        }
        if (($ubicacion !== null && mb_strlen($ubicacion) > 100) || ($especifica !== null && mb_strlen($especifica) > 100)) {
            return ['error' => 'Ubicación y ubicación específica admiten máximo 100 caracteres.', 'creado' => false];
        }

        $clave = $this->clave($codigo ?? $nombre);
        $grupo = $codigo !== null ? ($indice['porCodigo'][$clave] ?? []) : ($indice['porNombre'][$clave] ?? []);
        $productos = array_values($grupo);

        if (count($productos) !== 1) {
            $por = $codigo !== null ? 'código' : 'nombre';
            return [
                'error' => $productos === []
                    ? 'No hay un producto con ese ' . $por . '.'
                    : 'Hay más de un producto con ese ' . $por . '.',
                'creado' => false,
            ];
        }
        if (($productos[0]->tipo ?? '') === 'Servicio') {
            return ['error' => 'El producto es un servicio.', 'creado' => false];
        }

        $bodegas = array_values($indice['bodegas'][$this->clave($bodegaNombre)] ?? []);
        if ($bodegas === []) {
            return ['error' => 'No hay una bodega activa con ese nombre.', 'creado' => false];
        }

        $inventarios = Inventario::query()
            ->where('id_producto', $productos[0]->id)
            ->whereIn('id_bodega', array_map(fn ($bodega) => (int) $bodega->id, $bodegas))
            ->get();

        if ($inventarios->count() !== 1) {
            return [
                'error' => count($bodegas) > 1 && $inventarios->isNotEmpty()
                    ? 'Hay más de una bodega activa con ese nombre.'
                    : 'El producto no tiene inventario en esa bodega.',
                'creado' => false,
            ];
        }

        $inventario = $inventarios->first();
        $activa = $this->activa($fila['sin_existencias'] ?? null) ?? true;

        foreach ($inventario->ubicaciones as $existente) {
            if ($this->mismaUbicacion($existente, $ubicacion, $especifica, $detalle, $activa)) {
                return ['error' => null, 'creado' => false];
            }
        }

        $inventario->ubicaciones()->create([
            'ubicacion' => $ubicacion,
            'ubicacion_especifica' => $especifica,
            'detalle' => $detalle,
            'activa' => $activa,
        ]);

        return ['error' => null, 'creado' => true];
    }

    private function mismaUbicacion(object $existente, ?string $ubicacion, ?string $especifica, ?string $detalle, bool $activa): bool
    {
        return $this->clave($existente->ubicacion ?? '') === $this->clave($ubicacion ?? '')
            && $this->clave($existente->ubicacion_especifica ?? '') === $this->clave($especifica ?? '')
            && $this->clave($existente->detalle ?? '') === $this->clave($detalle ?? '')
            && (bool) $existente->activa === $activa;
    }

    private function indice(int $idEmpresa): array
    {
        $porCodigo = [];
        $porNombre = [];
        $productos = DB::table('productos')
            ->where('id_empresa', $idEmpresa)
            ->whereNull('deleted_at')
            ->get(['id', 'codigo', 'nombre', 'tipo']);

        foreach ($productos as $producto) {
            $codigo = $this->clave($producto->codigo ?? '');
            if ($codigo !== '') {
                $porCodigo[$codigo][(int) $producto->id] = $producto;
            }
            $nombre = $this->clave($producto->nombre ?? '');
            if ($nombre !== '') {
                $porNombre[$nombre][(int) $producto->id] = $producto;
            }
        }

        $bodegas = [];
        foreach (DB::table('sucursal_bodegas')->where('id_empresa', $idEmpresa)->where('activo', 1)->get(['id', 'nombre']) as $bodega) {
            $nombre = $this->clave($bodega->nombre ?? '');
            if ($nombre !== '') {
                $bodegas[$nombre][(int) $bodega->id] = $bodega;
            }
        }

        return compact('porCodigo', 'porNombre', 'bodegas');
    }

    /** Misma clave para el Excel y la base: sin espacios de más ni saltos de línea. */
    private function clave($valor): string
    {
        $limpio = preg_replace('/\s+/u', ' ', (string) $valor);

        return mb_strtolower(trim($limpio ?? (string) $valor));
    }

    private function tieneLugar(array $fila): bool
    {
        foreach (['ubicacion', 'ubicacion_especifica', 'detalle'] as $campo) {
            if ($this->presente($fila[$campo] ?? null)) {
                return true;
            }
        }

        return false;
    }

    private function filaVacia(array $fila): bool
    {
        foreach (['id_ubicacion', 'codigo', 'bodega', 'ubicacion', 'ubicacion_especifica', 'detalle', 'sin_existencias'] as $campo) {
            if ($this->presente($fila[$campo] ?? null)) {
                return false;
            }
        }

        return true;
    }

    private function presente($valor): bool
    {
        return $valor !== null && trim((string) $valor) !== '';
    }

    private function texto($valor): ?string
    {
        if (!$this->presente($valor)) {
            return null;
        }

        return trim((string) $valor);
    }

    private function entero($valor): int
    {
        if (!$this->presente($valor)) {
            return 0;
        }

        return (int) $valor;
    }

    /** null = no vino el dato. */
    private function activa($valor): ?bool
    {
        if (!$this->presente($valor)) {
            return null;
        }
        $texto = mb_strtolower(trim((string) $valor));
        if (in_array($texto, ['si', 'sí', '1', 'true', 's'], true)) {
            return false;
        }
        if (in_array($texto, ['no', '0', 'false', 'n'], true)) {
            return true;
        }

        return null;
    }
}

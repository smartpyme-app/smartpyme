<?php

namespace App\Services\Clinica;

use App\Models\Clinica\ProductoPerfil;
use App\Models\Inventario\Producto;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ProductoClinicoService
{
    /** @return list<array<string, mixed>> */
    public function buscarMedicamentos(int $idEmpresa, ?string $q, int $limite = 25): array
    {
        $limite = max(1, min(50, $limite));

        $consulta = Producto::withoutGlobalScopes()
            ->select('productos.id', 'productos.nombre', 'productos.codigo', 'productos.medida')
            ->join('clinica_producto_perfiles as cpp', 'cpp.id_producto', '=', 'productos.id')
            ->where('cpp.id_empresa', $idEmpresa)
            ->where('cpp.activo', true)
            ->where('productos.id_empresa', $idEmpresa)
            ->orderBy('productos.nombre')
            ->limit($limite);

        if ($q !== null && trim($q) !== '') {
            $termino = '%'.trim($q).'%';
            $consulta->where(function ($sub) use ($termino) {
                $sub->where('productos.nombre', 'like', $termino)
                    ->orWhere('productos.codigo', 'like', $termino);
            });
        }

        return $consulta->get()->map(function (Producto $producto) use ($idEmpresa) {
            $perfil = ProductoPerfil::query()
                ->where('id_empresa', $idEmpresa)
                ->where('id_producto', $producto->id)
                ->first();

            return [
                'id_producto' => (int) $producto->id,
                'nombre' => $producto->nombre,
                'codigo' => $producto->codigo,
                'medida' => $producto->medida,
                'via_habitual' => $perfil?->via_habitual,
                'unidad_dosis' => $perfil?->unidad_dosis,
                'controla_lote' => (bool) ($perfil?->controla_lote ?? false),
            ];
        })->all();
    }

    /** @param array<string, mixed> $datos */
    public function guardarPerfil(int $idEmpresa, int $idProducto, array $datos): ProductoPerfil
    {
        $producto = Producto::withoutGlobalScopes()
            ->where('id', $idProducto)
            ->where('id_empresa', $idEmpresa)
            ->first();

        if ($producto === null) {
            throw ValidationException::withMessages([
                'id_producto' => ['El producto no existe en esta empresa.'],
            ]);
        }

        $validado = Validator::make($datos, [
            'via_habitual' => ['nullable', 'string', 'max:80'],
            'unidad_dosis' => ['nullable', 'string', 'max:80'],
            'controla_lote' => ['sometimes', 'boolean'],
            'activo' => ['sometimes', 'boolean'],
        ])->validate();

        /** @var ProductoPerfil $perfil */
        $perfil = ProductoPerfil::query()->firstOrNew([
            'id_empresa' => $idEmpresa,
            'id_producto' => $idProducto,
        ]);

        $perfil->fill([
            'via_habitual' => $validado['via_habitual'] ?? null,
            'unidad_dosis' => $validado['unidad_dosis'] ?? null,
            'controla_lote' => (bool) ($validado['controla_lote'] ?? $perfil->controla_lote ?? false),
            'activo' => array_key_exists('activo', $validado) ? (bool) $validado['activo'] : true,
        ]);
        $perfil->save();

        return $perfil->fresh(['producto']);
    }

    public function obtenerPerfil(int $idEmpresa, int $idProducto): ?ProductoPerfil
    {
        return ProductoPerfil::query()
            ->where('id_empresa', $idEmpresa)
            ->where('id_producto', $idProducto)
            ->first();
    }
}

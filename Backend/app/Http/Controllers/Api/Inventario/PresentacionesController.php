<?php

namespace App\Http\Controllers\Api\Inventario;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Admin\Empresa;
use App\Models\Inventario\Producto;
use App\Models\Inventario\ProductoPresentacion;

class PresentacionesController extends Controller
{
    private function moduloPresentacionesActivo(): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }
        $empresa = Empresa::find($user->id_empresa);

        return $empresa ? $empresa->isModuloPresentaciones() : false;
    }

    private function denegarSiModuloInactivo()
    {
        if (!$this->moduloPresentacionesActivo()) {
            return response()->json([
                'message' => 'El módulo de presentaciones no está habilitado para esta empresa.',
            ], 403);
        }

        return null;
    }
    /**
     * Crea una nueva presentación para un producto.
     * POST /producto-presentaciones
     */
    public function store(Request $request)
    {
        if ($denegado = $this->denegarSiModuloInactivo()) {
            return $denegado;
        }

        $request->validate([
            'id_producto'      => 'required|integer|exists:productos,id',
            'id_unidad_medida' => 'required|integer|exists:unidades,id',
            'nombre_comercial' => 'required|string|max:255',
            'factor_conversion'=> 'required|numeric|min:0.000001',
            'precio_venta'     => 'nullable|numeric|min:0',
            'codigo_barras'    => 'nullable|string|max:255',
        ]);

        $presentacion = new ProductoPresentacion();
        $presentacion->fill([
            'id_producto'       => $request->id_producto,
            'id_unidad_medida'  => $request->id_unidad_medida,
            'nombre_comercial'  => $request->nombre_comercial,
            'factor_conversion' => $request->factor_conversion,
            'precio_venta'      => $request->precio_venta ?? 0,
            'codigo_barras'     => $request->codigo_barras,
        ]);
        $presentacion->save();

        // Devolver con la relación unidadMedida para que el front la muestre de inmediato
        $presentacion->load('unidadMedida');

        return response()->json($presentacion, 200);
    }

    /**
     * Actualiza una presentación existente.
     * PUT /producto-presentaciones/{id}
     */
    public function update(Request $request, $id)
    {
        if ($denegado = $this->denegarSiModuloInactivo()) {
            return $denegado;
        }

        $request->validate([
            'id_unidad_medida' => 'required|integer|exists:unidades,id',
            'nombre_comercial' => 'required|string|max:255',
            'factor_conversion'=> 'required|numeric|min:0.000001',
            'precio_venta'     => 'nullable|numeric|min:0',
            'codigo_barras'    => 'nullable|string|max:255',
        ]);

        $presentacion = ProductoPresentacion::findOrFail($id);
        $presentacion->update([
            'id_unidad_medida'  => $request->id_unidad_medida,
            'nombre_comercial'  => $request->nombre_comercial,
            'factor_conversion' => $request->factor_conversion,
            'precio_venta'      => $request->precio_venta ?? 0,
            'codigo_barras'     => $request->codigo_barras,
        ]);

        $presentacion->load('unidadMedida');

        return response()->json($presentacion, 200);
    }

    /**
     * Elimina una presentación.
     * DELETE /producto-presentaciones/{id}
     */
    public function delete($id)
    {
        if ($denegado = $this->denegarSiModuloInactivo()) {
            return $denegado;
        }

        $presentacion = ProductoPresentacion::findOrFail($id);
        $presentacion->delete();

        return response()->json($presentacion, 200);
    }
}

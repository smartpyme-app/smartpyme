<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Empresa;
use App\Models\Admin\ShopifyLocation;
use App\Models\Admin\Sucursal;
use App\Models\Inventario\Bodega;
use App\Services\ShopifyLocationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ShopifyLocationController extends Controller
{
    protected $locationService;

    public function __construct(ShopifyLocationService $locationService)
    {
        $this->locationService = $locationService;
    }

    public function index(Request $request)
    {
        $empresaId = Auth::user()->id_empresa;

        $locations = ShopifyLocation::withoutGlobalScope('empresa')
            ->where('id_empresa', $empresaId)
            ->with(['sucursal', 'bodega'])
            ->orderBy('id', 'asc')
            ->get();

        $sucursales = Sucursal::withoutGlobalScope('empresa')
            ->where('id_empresa', $empresaId)
            ->with(['bodegas'])
            ->where('activo', '1')
            ->get();

        return response()->json([
            'status' => 'success',
            'locations' => $locations,
            'sucursales' => $sucursales
        ]);
    }

    public function sync(Request $request)
    {
        $empresa = Empresa::find(Auth::user()->id_empresa);

        if (!$empresa || empty($empresa->shopify_store_url)) {
            return response()->json([
                'status' => 'error',
                'mensaje' => 'La empresa no tiene configurada la integración con Shopify.'
            ], 400);
        }

        $resultado = $this->locationService->syncLocationsFromShopify($empresa);

        if (!$resultado['success']) {
            return response()->json([
                'status' => 'error',
                'mensaje' => $resultado['mensaje']
            ], 400);
        }

        // Retornar lista completa actualizada
        return $this->index($request);
    }

    public function update(Request $request, $id)
    {
        $empresaId = Auth::user()->id_empresa;

        $location = ShopifyLocation::withoutGlobalScope('empresa')
            ->where('id_empresa', $empresaId)
            ->where('id', $id)
            ->first();

        if (!$location) {
            return response()->json([
                'status' => 'error',
                'mensaje' => 'Ubicación no encontrada.'
            ], 404);
        }

        $request->validate([
            'id_sucursal' => 'nullable|integer',
            'id_bodega' => 'nullable|integer',
            'sincronizar_stock' => 'nullable|boolean',
            'es_default' => 'nullable|boolean',
        ]);

        if ($request->has('id_sucursal')) {
            $location->id_sucursal = $request->input('id_sucursal') ?: null;

            // Actualizar también el shopify_location_id en la sucursal asignada
            if ($location->id_sucursal) {
                $sucursal = Sucursal::withoutGlobalScope('empresa')
                    ->where('id_empresa', $empresaId)
                    ->find($location->id_sucursal);

                if ($sucursal) {
                    $sucursal->shopify_location_id = $location->shopify_location_id;
                    $sucursal->save();
                }
            }
        }

        if ($request->has('id_bodega')) {
            $location->id_bodega = $request->input('id_bodega') ?: null;
        }

        if ($request->has('sincronizar_stock')) {
            $location->sincronizar_stock = (bool)$request->input('sincronizar_stock');
        }

        if ($request->has('es_default') && $request->input('es_default')) {
            // Desmarcar las otras como default
            ShopifyLocation::withoutGlobalScope('empresa')
                ->where('id_empresa', $empresaId)
                ->where('id', '!=', $location->id)
                ->update(['es_default' => false]);

            $location->es_default = true;
        } elseif ($request->has('es_default')) {
            $location->es_default = (bool)$request->input('es_default');
        }

        $location->save();

        return response()->json([
            'status' => 'success',
            'mensaje' => 'Mapeo de ubicación actualizado exitosamente.',
            'location' => $location->load(['sucursal', 'bodega'])
        ]);
    }

    public function crearSucursal(Request $request, $id)
    {
        $empresa = Empresa::find(Auth::user()->id_empresa);

        $location = ShopifyLocation::withoutGlobalScope('empresa')
            ->where('id_empresa', $empresa->id)
            ->where('id', $id)
            ->first();

        if (!$location) {
            return response()->json([
                'status' => 'error',
                'mensaje' => 'Ubicación no encontrada.'
            ], 404);
        }

        $resultado = $this->locationService->crearSucursalYBodega($location, $empresa);

        if (!$resultado['success']) {
            return response()->json([
                'status' => 'error',
                'mensaje' => $resultado['mensaje']
            ], 400);
        }

        $locations = ShopifyLocation::withoutGlobalScope('empresa')
            ->where('id_empresa', $empresa->id)
            ->with(['sucursal', 'bodega'])
            ->orderBy('id', 'asc')
            ->get();

        $sucursales = Sucursal::withoutGlobalScope('empresa')
            ->where('id_empresa', $empresa->id)
            ->with(['bodegas'])
            ->where('activo', '1')
            ->get();

        return response()->json([
            'status' => 'success',
            'mensaje' => $resultado['mensaje'],
            'ya_existia' => $resultado['ya_existia'] ?? false,
            'location' => $location->fresh(['sucursal', 'bodega']),
            'locations' => $locations,
            'sucursales' => $sucursales,
        ]);
    }
}

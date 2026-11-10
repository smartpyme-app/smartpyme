<?php

namespace App\Http\Controllers\Api\Inventario;

use App\Http\Controllers\Controller;
use App\Services\Inventario\ConsignaMovimientoService;
use Illuminate\Http\Request;

class ConsignaKardexController extends Controller
{
    public function index(Request $request, ConsignaMovimientoService $consignaMovimientoService)
    {
        $user = auth()->user();
        if (! $user?->id_empresa) {
            return response()->json(['error' => 'Usuario sin empresa asociada'], 400);
        }

        $empresa = $user->empresa()->first();
        if (! $empresa || ! $empresa->isModuloConsignacionesLedgerActivo()) {
            return response()->json(['error' => 'Módulo de consignaciones no activo para esta empresa'], 403);
        }

        $movimientos = $consignaMovimientoService->listar($request, (int) $user->id_empresa);

        return response()->json($movimientos, 200);
    }
}

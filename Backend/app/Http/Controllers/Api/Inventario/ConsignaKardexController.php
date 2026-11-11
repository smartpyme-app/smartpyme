<?php

namespace App\Http\Controllers\Api\Inventario;

use App\Exports\ConsignaKardexExport;
use App\Http\Controllers\Controller;
use App\Services\Inventario\ConsignaMovimientoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class ConsignaKardexController extends Controller
{
    public function lotes(Request $request, ConsignaMovimientoService $consignaMovimientoService)
    {
        $user = auth()->user();
        if (! $user?->id_empresa) {
            return response()->json(['error' => 'Usuario sin empresa asociada'], 400);
        }

        $empresa = $user->empresa()->first();
        if (! $empresa || ! $empresa->isModuloConsignacionesLedgerActivo()) {
            return response()->json(['error' => 'Módulo de consignaciones no activo para esta empresa'], 403);
        }

        $validator = Validator::make($request->all(), [
            'id_producto' => 'required|integer',
            'id_bodega' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        return response()->json(
            $consignaMovimientoService->lotesParaKardex(
                (int) $user->id_empresa,
                (int) $request->id_producto,
                (int) $request->id_bodega
            ),
            200
        );
    }

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

    public function export(Request $request)
    {
        $user = auth()->user();
        if (! $user?->id_empresa) {
            return response()->json(['error' => 'Usuario sin empresa asociada'], 400);
        }

        $empresa = $user->empresa()->first();
        if (! $empresa || ! $empresa->isModuloConsignacionesLedgerActivo()) {
            return response()->json(['error' => 'Módulo de consignaciones no activo para esta empresa'], 403);
        }

        $export = new ConsignaKardexExport($request, (int) $user->id_empresa);

        return Excel::download($export, 'consigna-kardex.xlsx');
    }
}

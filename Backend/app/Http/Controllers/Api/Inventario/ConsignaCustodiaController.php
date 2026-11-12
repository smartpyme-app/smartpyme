<?php

namespace App\Http\Controllers\Api\Inventario;

use App\Constants\ConsignaMovimientoTipoConstants;
use App\Http\Controllers\Controller;
use App\Models\Inventario\ConsignaCustodia;
use App\Exports\ConsignaCustodiaVentasExport;
use App\Services\Inventario\ConsignaCustodiaService;
use App\Services\Inventario\ConsignaMovimientoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class ConsignaCustodiaController extends Controller
{
    public function exportarVentas(Request $request)
    {
        $user = auth()->user();
        if (! $user?->id_empresa) {
            return response()->json(['error' => 'Usuario sin empresa asociada'], 400);
        }

        $empresa = $user->empresa()->first();
        if (! $empresa || ! $empresa->isModuloConsignacionesLedgerActivo()) {
            return response()->json(['error' => 'Módulo de consignaciones no activo para esta empresa'], 403);
        }

        $export = new ConsignaCustodiaVentasExport();
        $export->filter($request);

        return Excel::download($export, 'reporte-ventas-consigna.xlsx');
    }

    public function ventas(Request $request, ConsignaCustodiaService $custodiaService)
    {
        $user = auth()->user();
        if (! $user?->id_empresa) {
            return response()->json(['error' => 'Usuario sin empresa asociada'], 400);
        }

        $empresa = $user->empresa()->first();
        if (! $empresa || ! $empresa->isModuloConsignacionesLedgerActivo()) {
            return response()->json(['error' => 'Módulo de consignaciones no activo para esta empresa'], 403);
        }

        return response()->json(
            $custodiaService->listarVentasConsigna($request, (int) $user->id_empresa),
            200
        );
    }

    public function index(Request $request, ConsignaCustodiaService $custodiaService)
    {
        $user = auth()->user();
        if (! $user?->id_empresa) {
            return response()->json(['error' => 'Usuario sin empresa asociada'], 400);
        }

        $empresa = $user->empresa()->first();
        if (! $empresa || ! $empresa->isModuloConsignacionesLedgerActivo()) {
            return response()->json(['error' => 'Módulo de consignaciones no activo para esta empresa'], 403);
        }

        return response()->json(
            $custodiaService->listar($request, (int) $user->id_empresa),
            200
        );
    }

    public function conteo(
        Request $request,
        ConsignaMovimientoService $movimientoService,
        ConsignaCustodiaService $custodiaService
    ) {
        $user = auth()->user();
        if (! $user?->id_empresa) {
            return response()->json(['error' => 'Usuario sin empresa asociada'], 400);
        }

        $empresa = $user->empresa()->first();
        if (! $empresa || ! $empresa->isModuloConsignacionesLedgerActivo()) {
            return response()->json(['error' => 'Módulo de consignaciones no activo para esta empresa'], 403);
        }

        $validator = Validator::make($request->all(), [
            'id_cliente' => 'required|integer',
            'id_producto' => 'required|integer',
            'id_bodega' => 'required|integer',
            'cantidad_fisica' => 'required|numeric|min:0',
            'nota' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $idEmpresa = (int) $user->id_empresa;
        $idCliente = (int) $request->id_cliente;
        $idProducto = (int) $request->id_producto;
        $idBodega = (int) $request->id_bodega;
        $fisica = (float) $request->cantidad_fisica;

        $sistema = (float) ConsignaCustodia::query()
            ->forEmpresa($idEmpresa)
            ->where('id_cliente', $idCliente)
            ->where('id_producto', $idProducto)
            ->where('id_bodega', $idBodega)
            ->value('cantidad');

        $delta = $fisica - $sistema;
        if (abs($delta) < 0.00001) {
            return response()->json([
                'ok' => true,
                'mensaje' => 'El conteo coincide con el sistema; no hay ajuste.',
                'cantidad_sistema' => $sistema,
            ], 200);
        }

        $nota = $request->input('nota')
            ?: sprintf('Conteo físico: sistema %.4f, contado %.4f', $sistema, $fisica);

        $movimiento = $movimientoService->registrar($idEmpresa, [
            'tipo' => ConsignaMovimientoTipoConstants::AJUSTE_CONSIGNA,
            'cantidad' => $delta,
            'id_producto' => $idProducto,
            'id_bodega' => $idBodega,
            'id_cliente' => $idCliente,
            'nota' => $nota,
            'idempotency_key' => 'conteo-' . $idCliente . '-' . $idProducto . '-' . $idBodega . '-' . now()->format('YmdHis'),
        ]);

        return response()->json([
            'ok' => true,
            'cantidad_sistema' => $sistema,
            'cantidad_fisica' => $fisica,
            'delta' => $delta,
            'movimiento' => $movimiento,
        ], 200);
    }
}

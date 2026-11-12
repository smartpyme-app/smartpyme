<?php

namespace App\Http\Controllers\Api\Inventario;

use App\Exports\ConsignaHistorialExport;
use App\Http\Controllers\Controller;
use App\Services\Inventario\ConsignaHistorialService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ConsignaHistorialController extends Controller
{
    public function __construct(
        private ConsignaHistorialService $historialService
    ) {}

    public function index(Request $request)
    {
        $ctx = $this->empresaLedgerActiva();
        if (isset($ctx['error'])) {
            return $ctx['error'];
        }

        $alcance = $this->historialService->alcanceDesdeRequest($request);
        if ($rechazo = $this->validarTercero($request, $alcance, true)) {
            return $rechazo;
        }

        return response()->json(
            $this->historialService->reporte($request, $ctx['id_empresa'], $ctx['empresa']),
            200
        );
    }

    public function exportar(Request $request)
    {
        $ctx = $this->empresaLedgerActiva();
        if (isset($ctx['error'])) {
            return $ctx['error'];
        }

        $alcance = $this->historialService->alcanceDesdeRequest($request);
        if ($rechazo = $this->validarTercero($request, $alcance, false)) {
            return $rechazo;
        }

        $payload = $this->historialService->reporte($request, $ctx['id_empresa'], $ctx['empresa']);
        $nombre = $alcance === 'compras'
            ? 'historial-consigna-compras.xlsx'
            : 'historial-consigna-ventas.xlsx';

        return Excel::download(
            new ConsignaHistorialExport($payload, $alcance),
            $nombre
        );
    }

    private function validarTercero(Request $request, string $alcance, bool $listarSinFiltro): ?\Illuminate\Http\JsonResponse
    {
        if ($alcance === 'ventas') {
            if (! $request->filled('id_cliente')) {
                return response()->json([
                    'error' => $listarSinFiltro
                        ? 'Seleccione un cliente para ver el historial.'
                        : 'Debe seleccionar un cliente para exportar.',
                ], 422);
            }

            return null;
        }

        if (! $request->filled('id_proveedor')) {
            return response()->json([
                'error' => $listarSinFiltro
                    ? 'Seleccione un proveedor para ver el historial.'
                    : 'Debe seleccionar un proveedor para exportar.',
            ], 422);
        }

        return null;
    }

    private function empresaLedgerActiva(): array
    {
        $user = auth()->user();
        if (! $user?->id_empresa) {
            return ['error' => response()->json(['error' => 'Usuario sin empresa asociada'], 400)];
        }

        $empresa = $user->empresa()->first();
        if (! $empresa || ! $empresa->isModuloConsignacionesLedgerActivo()) {
            return ['error' => response()->json(['error' => 'Módulo de consignaciones no activo para esta empresa'], 403)];
        }

        return ['empresa' => $empresa, 'id_empresa' => (int) $user->id_empresa];
    }
}

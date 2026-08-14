<?php

namespace App\Http\Controllers\Api\Bonos;

use App\Http\Controllers\Controller;
use App\Services\Bonos\BonoGeneradoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BonoGeneradoController extends Controller
{
    public function __construct(
        private BonoGeneradoService $generadoService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'estado' => 'nullable|string|in:pendiente,aprobado,pagado',
            'periodo_inicio' => 'nullable|date',
            'periodo_fin' => 'nullable|date',
            'id_vendedor' => 'nullable|integer|min:1',
        ]);

        $bonos = $this->generadoService->listar(
            (int) $request->user()->id_empresa,
            $validated['estado'] ?? null,
            $validated['periodo_inicio'] ?? null,
            $validated['periodo_fin'] ?? null,
            isset($validated['id_vendedor']) ? (int) $validated['id_vendedor'] : null,
        );

        return response()->json([
            'success' => true,
            'data' => $bonos,
        ]);
    }

    public function storeManual(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_regla' => 'required|integer|min:1',
            'id_vendedor' => 'required|integer|min:1',
            'periodo_inicio' => 'required|date',
            'periodo_fin' => 'required|date',
            'monto' => 'required|numeric|min:0.01',
            'monto_ventas_base' => 'nullable|numeric|min:0',
        ]);

        $bono = $this->generadoService->crearManual(
            (int) $request->user()->id_empresa,
            $validated
        );

        return response()->json([
            'success' => true,
            'data' => $bono,
            'message' => 'Bono manual creado correctamente.',
        ], 201);
    }

    public function aprobar(Request $request, int $id): JsonResponse
    {
        $bono = $this->generadoService->aprobar(
            (int) $request->user()->id_empresa,
            $id,
            (int) $request->user()->id
        );

        return response()->json([
            'success' => true,
            'data' => $bono,
            'message' => 'Bono aprobado correctamente.',
        ]);
    }

    public function pagar(Request $request, int $id): JsonResponse
    {
        $bono = $this->generadoService->pagar(
            (int) $request->user()->id_empresa,
            $id
        );

        return response()->json([
            'success' => true,
            'data' => $bono,
            'message' => 'Bono marcado como pagado.',
        ]);
    }

    public function comprobantePdf(Request $request, int $id)
    {
        $datos = $this->generadoService->datosComprobante(
            (int) $request->user()->id_empresa,
            $id
        );

        $pdf = app('dompdf.wrapper')->loadView('reportes.bonos.comprobante', $datos);
        $pdf->setPaper('US Letter', 'portrait');

        return $pdf->stream('comprobante-bono-' . $id . '.pdf');
    }
}

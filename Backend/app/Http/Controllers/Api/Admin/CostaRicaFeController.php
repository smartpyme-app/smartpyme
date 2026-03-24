<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CostaRica\EmitirFeCrDevolucionRequest;
use App\Http\Requests\CostaRica\EmitirFeCrNotaDebitoRequest;
use App\Http\Requests\CostaRica\EmitirFeCrVentaRequest;
use App\Services\FacturacionElectronica\CostaRica\CostaRicaFeEmitService;
use Illuminate\Http\JsonResponse;
use Throwable;

class CostaRicaFeController extends Controller
{
    public function emitirFactura(EmitirFeCrVentaRequest $request, CostaRicaFeEmitService $emitService): JsonResponse
    {
        try {
            $resultado = $emitService->emitirFacturaDesdeVenta((int) $request->id);

            return response()->json($resultado, 200);
        } catch (Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function emitirTiquete(EmitirFeCrVentaRequest $request, CostaRicaFeEmitService $emitService): JsonResponse
    {
        try {
            $resultado = $emitService->emitirTiqueteDesdeVenta((int) $request->id);

            return response()->json($resultado, 200);
        } catch (Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function emitirNotaCreditoDevolucion(EmitirFeCrDevolucionRequest $request, CostaRicaFeEmitService $emitService): JsonResponse
    {
        try {
            $resultado = $emitService->emitirNotaCreditoDesdeDevolucion((int) $request->id);

            return response()->json($resultado, 200);
        } catch (Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function emitirNotaDebito(EmitirFeCrNotaDebitoRequest $request, CostaRicaFeEmitService $emitService): JsonResponse
    {
        try {
            $resultado = $emitService->emitirNotaDebitoDesdeVenta(
                (int) $request->id,
                (string) ($request->motivo ?? ''),
                (float) $request->monto_linea
            );

            return response()->json($resultado, 200);
        } catch (Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function consultarEstadoVenta(EmitirFeCrVentaRequest $request, CostaRicaFeEmitService $emitService): JsonResponse
    {
        try {
            $resultado = $emitService->consultarEstadoVenta((int) $request->id);

            return response()->json($resultado, 200);
        } catch (Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }
    }
}

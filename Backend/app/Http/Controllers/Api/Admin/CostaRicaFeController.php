<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\CostaRica\CostaRicaFeEmisionFallidaException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CostaRica\EmitirFeCrCompraRequest;
use App\Http\Requests\CostaRica\EmitirFeCrDevolucionRequest;
use App\Http\Requests\CostaRica\EmitirFeCrGastoRequest;
use App\Http\Requests\CostaRica\EmitirFeCrNotaDebitoRequest;
use App\Http\Requests\CostaRica\EmitirFeCrVentaRequest;
use App\Services\FacturacionElectronica\CostaRica\CostaRicaFeEmitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class CostaRicaFeController extends Controller
{
    public function emitirFactura(EmitirFeCrVentaRequest $request, CostaRicaFeEmitService $emitService): JsonResponse
    {
        try {
            $resultado = $emitService->emitirFacturaDesdeVenta((int) $request->id);

            return response()->json($resultado, 200);
        } catch (CostaRicaFeEmisionFallidaException $e) {
            return response()->json($this->payloadErrorEmisionFeCr($e), 422);
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
        } catch (CostaRicaFeEmisionFallidaException $e) {
            return response()->json($this->payloadErrorEmisionFeCr($e), 422);
        } catch (Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function emitirFacturaElectronicaCompra(EmitirFeCrCompraRequest $request, CostaRicaFeEmitService $emitService): JsonResponse
    {
        try {
            $resultado = $emitService->emitirFacturaElectronicaCompraDesdeCompra((int) $request->id);

            return response()->json($resultado, 200);
        } catch (CostaRicaFeEmisionFallidaException $e) {
            return response()->json($this->payloadErrorEmisionFeCr($e), 422);
        } catch (Throwable $e) {
            Log::error('FE CR emitirFacturaElectronicaCompra', [
                'compra_id' => $request->id,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function emitirFacturaElectronicaGasto(EmitirFeCrGastoRequest $request, CostaRicaFeEmitService $emitService): JsonResponse
    {
        try {
            $resultado = $emitService->emitirFacturaElectronicaCompraDesdeGasto((int) $request->id);

            return response()->json($resultado, 200);
        } catch (CostaRicaFeEmisionFallidaException $e) {
            return response()->json($this->payloadErrorEmisionFeCr($e), 422);
        } catch (Throwable $e) {
            Log::error('FE CR emitirFacturaElectronicaGasto', [
                'gasto_id' => $request->id,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

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
        } catch (CostaRicaFeEmisionFallidaException $e) {
            return response()->json($this->payloadErrorEmisionFeCr($e), 422);
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
        } catch (CostaRicaFeEmisionFallidaException $e) {
            return response()->json($this->payloadErrorEmisionFeCr($e), 422);
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

    public function consultarEstadoDevolucion(EmitirFeCrDevolucionRequest $request, CostaRicaFeEmitService $emitService): JsonResponse
    {
        try {
            $resultado = $emitService->consultarEstadoDevolucion((int) $request->id);

            return response()->json($resultado, 200);
        } catch (Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function consultarEstadoCompra(EmitirFeCrCompraRequest $request, CostaRicaFeEmitService $emitService): JsonResponse
    {
        try {
            $resultado = $emitService->consultarEstadoCompra((int) $request->id);

            return response()->json($resultado, 200);
        } catch (Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function consultarEstadoGasto(EmitirFeCrGastoRequest $request, CostaRicaFeEmitService $emitService): JsonResponse
    {
        try {
            $resultado = $emitService->consultarEstadoGasto((int) $request->id);

            return response()->json($resultado, 200);
        } catch (Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function consultarEstadoNotaDebitoVenta(EmitirFeCrVentaRequest $request, CostaRicaFeEmitService $emitService): JsonResponse
    {
        try {
            $resultado = $emitService->consultarEstadoNotaDebitoVenta((int) $request->id);

            return response()->json($resultado, 200);
        } catch (Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * @return array{
     *   error: string,
     *   documento: array<string, mixed>,
     *   clave: ?string,
     *   detalle_estado: ?array<string, mixed>,
     *   xml_comprobante: ?string,
     *   xml_comprobante_firmado: ?string
     * }
     */
    private function payloadErrorEmisionFeCr(CostaRicaFeEmisionFallidaException $e): array
    {
        $msg = $e->getMessage();

        return [
            'error' => $msg,
            'message' => $msg,
            'documento' => $e->getDocumento(),
            'clave' => $e->getClave(),
            'detalle_estado' => $e->getDetalleEstado(),
            'xml_comprobante' => $e->getXmlComprobante(),
            'xml_comprobante_firmado' => $e->getXmlComprobanteFirmado(),
        ];
    }
}

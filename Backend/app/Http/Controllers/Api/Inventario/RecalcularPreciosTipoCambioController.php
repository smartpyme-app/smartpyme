<?php

namespace App\Http\Controllers\Api\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Admin\Empresa;
use App\Services\Inventario\RecalcularPreciosTipoCambioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class RecalcularPreciosTipoCambioController extends Controller
{
    public function show(RecalcularPreciosTipoCambioService $service): JsonResponse
    {
        $empresa = Empresa::findOrFail(auth()->user()->id_empresa);

        return response()->json($service->snapshot($empresa));
    }

    public function guardar(Request $request, RecalcularPreciosTipoCambioService $service): JsonResponse
    {
        $empresa = Empresa::findOrFail(auth()->user()->id_empresa);
        try {
            return response()->json($service->guardarVenta($empresa, (float) $request->input('exchange_rate')));
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function recalcular(Request $request, RecalcularPreciosTipoCambioService $service): JsonResponse
    {
        $empresa = Empresa::findOrFail(auth()->user()->id_empresa);
        try {
            return response()->json($service->recalcular(
                $empresa,
                (float) $request->input('exchange_rate'),
                (bool) $request->boolean('aplicar_productos'),
                (bool) $request->boolean('aplicar_servicios'),
            ));
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}

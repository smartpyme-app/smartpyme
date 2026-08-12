<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Empresa;
use App\Services\Moneda\MonedaPaisService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Config / preview de tipo de cambio según pais_configuracion (módulo moneda).
 */
class MonedaConfigController extends Controller
{
    public function preview(Request $request, MonedaPaisService $service): JsonResponse
    {
        $empresa = Empresa::findOrFail(auth()->user()->id_empresa);

        try {
            $fecha = $request->filled('fecha')
                ? Carbon::parse($request->input('fecha'))
                : now();
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Fecha inválida.'], 422);
        }

        $preview = $service->preview($empresa, $fecha);
        $status = $preview['error'] ? 422 : 200;

        return response()->json($preview, $status);
    }

    public function config(Request $request, MonedaPaisService $service): JsonResponse
    {
        $empresa = Empresa::findOrFail(auth()->user()->id_empresa);
        $cfg = $service->configForEmpresa($empresa);

        return response()->json([
            'moneda_funcional' => $cfg['moneda_funcional'] ?? null,
            'monedas_documento' => $cfg['monedas_documento'] ?? [],
            'fuente' => $cfg['fuente'] ?? 'manual',
            'permitir_editar' => (bool) ($cfg['permitir_editar'] ?? false),
            'rate_manual' => $cfg['rate_manual'] ?? null,
            'rate_del_dia' => $cfg['rate_del_dia'] ?? null,
            'api' => $cfg['api'] ?? null,
        ], 200);
    }
}

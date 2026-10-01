<?php

namespace App\Http\Controllers\Api\Ventas;

use App\Http\Controllers\Controller;
use App\Models\Ventas\Venta;
use App\Services\Ventas\VentasRecurrentesEmpresaConfig;
use Illuminate\Http\Request;

class VentaRecurrenciaController extends Controller
{
    public function preferencias()
    {
        $empresa = auth()->user()->empresa;
        if (!$empresa) {
            return response()->json(['error' => 'Empresa no encontrada.'], 404);
        }

        return response()->json(VentasRecurrentesEmpresaConfig::preferencias($empresa), 200);
    }

    public function guardar(Request $request, $id)
    {
        $datos = $request->validate([
            'frecuencia' => 'required|in:mensual,anual',
            'pausada' => 'required|boolean',
        ]);

        $empresa = auth()->user()->empresa;
        if (!$empresa || !VentasRecurrentesEmpresaConfig::activo($empresa)) {
            return response()->json(['error' => 'Activa las ventas recurrentes automáticas en Preferencias del sistema.'], 403);
        }

        $venta = Venta::where('id', $id)->firstOrFail();

        if ($venta->id_venta_plantilla) {
            return response()->json(['error' => 'Configura la venta original, no esta copia.'], 422);
        }

        if ($venta->estado === 'Anulada') {
            return response()->json(['error' => 'No se puede programar una venta anulada.'], 422);
        }

        $venta->frecuencia_recurrencia = $datos['frecuencia'];
        $venta->recurrencia_pausada = $request->boolean('pausada');
        $venta->recurrente = '1';
        $venta->save();

        return response()->json($venta, 200);
    }

    public function guardarPreferencias(Request $request)
    {
        $datos = $request->validate([
            'activo' => 'required|boolean',
            'correo_resumen' => 'required|email|max:255',
            'generacion_pausada' => 'required|boolean',
        ]);

        $empresa = auth()->user()->empresa;
        if (!$empresa) {
            return response()->json(['error' => 'Empresa no encontrada.'], 404);
        }

        VentasRecurrentesEmpresaConfig::guardarPreferencias(
            $empresa,
            $request->boolean('activo'),
            $datos['correo_resumen'],
            $request->boolean('generacion_pausada'),
        );

        return response()->json([
            'configuracion' => VentasRecurrentesEmpresaConfig::preferencias($empresa->fresh()),
        ], 200);
    }
}

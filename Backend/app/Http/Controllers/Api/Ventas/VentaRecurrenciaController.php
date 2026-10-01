<?php

namespace App\Http\Controllers\Api\Ventas;

use App\Http\Controllers\Controller;
use App\Models\Admin\EmpresaFuncionalidad;
use App\Models\Admin\Funcionalidad;
use App\Models\Ventas\Venta;
use App\Services\Ventas\GenerarVentasRecurrentesService;
use Illuminate\Http\Request;

class VentaRecurrenciaController extends Controller
{
    public function guardar(Request $request, $id)
    {
        $datos = $request->validate([
            'frecuencia' => 'required|in:mensual,anual',
            'pausada' => 'required|boolean',
        ]);

        if (!$this->asignacionActiva(auth()->user()->id_empresa)) {
            return response()->json(['error' => 'Activa la recurrencia en la empresa.'], 403);
        }

        $venta = Venta::where('id', $id)->firstOrFail();

        if ($venta->id_venta_plantilla) {
            return response()->json(['error' => 'Configura la venta original, no esta copia.'], 422);
        }

        if (empty($venta->sello_mh)) {
            return response()->json(['error' => 'Emite el DTE antes de programarla.'], 422);
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
            'correo_resumen' => 'required|email|max:255',
            'generacion_pausada' => 'required|boolean',
        ]);

        $user = auth()->user();
        $asignacion = $this->asignacionActiva($user->id_empresa);
        if (!$asignacion) {
            return response()->json(['error' => 'Activa la recurrencia en la empresa.'], 403);
        }

        $config = $asignacion->configuracion ?? [];
        $config['correo_resumen'] = $datos['correo_resumen'];
        $config['generacion_pausada'] = $request->boolean('generacion_pausada');
        $asignacion->configuracion = $config;
        $asignacion->save();

        return response()->json(['configuracion' => $asignacion->configuracion], 200);
    }

    private function asignacionActiva(int $idEmpresa): ?EmpresaFuncionalidad
    {
        $funcionalidad = Funcionalidad::where('slug', GenerarVentasRecurrentesService::SLUG)->first();
        if (!$funcionalidad) {
            return null;
        }

        return EmpresaFuncionalidad::where('id_empresa', $idEmpresa)
            ->where('id_funcionalidad', $funcionalidad->id)
            ->where('activo', 1)
            ->first();
    }
}

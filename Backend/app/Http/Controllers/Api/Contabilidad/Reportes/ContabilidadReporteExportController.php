<?php

namespace App\Http\Controllers\Api\Contabilidad\Reportes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contabilidad\EncolarContabilidadReporteRequest;
use App\Jobs\GenerarReporteAutomaticoJob;
use App\Models\Admin\ReporteExportacion;
use Illuminate\Support\Facades\Auth;

class ContabilidadReporteExportController extends Controller
{
    public function store(EncolarContabilidadReporteRequest $request)
    {
        $exportacion = ReporteExportacion::create([
            'id_empresa' => Auth::user()->id_empresa,
            'id_usuario' => Auth::id(),
            'id_configuracion' => null,
            'tipo_reporte' => $request->input('tipo_reporte'),
            'parametros' => $request->input('parametros') ?? [],
            'modo' => ReporteExportacion::MODO_DOWNLOAD,
            'formato' => $request->input('formato'),
            'estado' => ReporteExportacion::ESTADO_PENDING,
            'fecha_inicio' => $request->input('fecha_inicio'),
            'fecha_fin' => $request->input('fecha_fin'),
            'sucursales' => null,
            'destinatarios' => null,
        ]);

        GenerarReporteAutomaticoJob::dispatch($exportacion->id);

        return response()->json([
            'id' => $exportacion->id,
            'estado' => $exportacion->estado,
            'message' => 'El reporte se está generando. La descarga iniciará cuando esté listo.',
        ], 202);
    }
}

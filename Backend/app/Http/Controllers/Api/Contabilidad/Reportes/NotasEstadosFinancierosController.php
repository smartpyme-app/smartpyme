<?php

namespace App\Http\Controllers\Api\Contabilidad\Reportes;

use App\Exports\Contabilidad\NotasEstadosFinancierosExport;
use App\Http\Controllers\Controller;
use App\Models\Admin\Empresa;
use App\Models\Contabilidad\NotasEstadosFinancieros;
use App\Services\Contabilidad\BalanceGeneralNiifSvPresenter;
use App\Services\Contabilidad\CambiosPatrimonioNiifSvPresenter;
use App\Services\Contabilidad\EstadoResultadosNiifSvPresenter;
use App\Services\Contabilidad\FlujoEfectivoHibridoNiifSvPresenter;
use App\Services\Contabilidad\NotasEstadosFinancieros\NotasEstadosFinancierosCatalog;
use App\Services\Contabilidad\NotasEstadosFinancieros\NotasEstadosFinancierosPermisos;
use App\Services\Contabilidad\NotasEstadosFinancieros\NotasEstadosFinancierosService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use PDF;

class NotasEstadosFinancierosController extends Controller
{
    public function __construct(
        private NotasEstadosFinancierosService $service,
    ) {}

    public function generar(Request $request)
    {
        $this->autorizarVer();

        $params = $this->validarParametros($request);
        $params['empresa_id'] = auth()->user()->id_empresa;

        if ($request->boolean('guardar')) {
            $registro = $this->service->guardarBorrador($params);
            $payload = $this->service->generar($params, $registro);
        } else {
            $payload = $this->service->generar($params);
        }

        return response()->json($payload, 200);
    }

    public function show(int $id)
    {
        $this->autorizarVer();
        $registro = NotasEstadosFinancieros::findOrFail($id);

        return response()->json([
            'id' => $registro->id,
            'estado' => $registro->estado,
            'notas' => $registro->notas_generadas,
            'completitud' => $registro->completitud,
            'validaciones_cruzadas' => $registro->validaciones_cruzadas,
            'contenido_manual' => $registro->contenido_manual,
            'fecha_inicio' => $registro->fecha_inicio?->toDateString(),
            'fecha_fin' => $registro->fecha_fin?->toDateString(),
        ], 200);
    }

    public function actualizarManual(Request $request, int $id)
    {
        if (! NotasEstadosFinancierosPermisos::puedeEditarManual()) {
            abort(403, 'No tiene permiso para editar notas manuales.');
        }

        $registro = NotasEstadosFinancieros::findOrFail($id);
        if ($registro->estado === 'emitido') {
            return response()->json(['error' => 'No se puede editar un documento emitido.'], 422);
        }

        $request->validate([
            'contenido_manual' => 'required|array',
        ]);

        $params = [
            'empresa_id' => $registro->id_empresa,
            'periodo_actual' => $registro->periodo_actual,
            'fecha_inicio' => $registro->fecha_inicio->toDateString(),
            'fecha_fin' => $registro->fecha_fin->toDateString(),
            'fecha_aprobacion_junta' => $registro->fecha_aprobacion_junta?->toDateString(),
            'incluir_comparativo' => $registro->incluir_comparativo,
            'periodo_anterior' => $registro->periodo_anterior,
            'nivel_detalle' => $registro->nivel_detalle,
            'notas_a_incluir' => $registro->notas_a_incluir,
            'configuracion' => $registro->configuracion,
            'contenido_manual' => $request->input('contenido_manual'),
        ];

        $registro->contenido_manual = $request->input('contenido_manual');
        $payload = $this->service->generar($params, $registro);

        return response()->json($payload, 200);
    }

    public function emitir(int $id)
    {
        if (! NotasEstadosFinancierosPermisos::puedeEmitir()) {
            abort(403, 'No tiene permiso para emitir las notas.');
        }

        $registro = NotasEstadosFinancieros::findOrFail($id);
        $completitud = $registro->completitud ?? [];

        if (! ($completitud['puede_emitir'] ?? false)) {
            return response()->json([
                'error' => 'Las notas bloqueantes (1, 2 y 3) deben estar completas antes de emitir.',
                'completitud' => $completitud,
            ], 422);
        }

        $registro->update([
            'estado' => 'emitido',
            'id_usuario_emision' => Auth::id(),
            'fecha_emision' => now(),
        ]);

        return response()->json(['message' => 'Notas emitidas correctamente.', 'id' => $registro->id], 200);
    }

    public function exportar(Request $request, string $fecha_inicio, string $fecha_fin, string $type)
    {
        $this->autorizarVer();
        $params = $this->validarParametros($request->merge([
            'fecha_inicio' => $fecha_inicio,
            'fecha_fin' => $fecha_fin,
        ]));
        $params['empresa_id'] = auth()->user()->id_empresa;

        $payload = $this->service->generar($params);
        $empresa = Empresa::findOrFail($params['empresa_id']);

        if ($type === 'pdf') {
            $pdf = PDF::loadView('reportes.contabilidad.notas_estados_financieros', [
                'notas' => $payload,
                'empresa' => $empresa,
                'fecha_inicio' => $fecha_inicio,
                'fecha_fin' => $fecha_fin,
            ]);
            $pdf->setPaper('US Letter', 'portrait');

            return $pdf->stream();
        }

        $fname = 'notas_estados_financieros_' . $fecha_inicio . '_' . $fecha_fin . '.xlsx';

        return Excel::download(
            new NotasEstadosFinancierosExport($payload, (string) $empresa->nombre),
            $fname
        );
    }

    public function exportarCompletos(Request $request, string $fecha_inicio, string $fecha_fin)
    {
        $this->autorizarVer();
        $empresaId = auth()->user()->id_empresa;
        $empresa = Empresa::findOrFail($empresaId);
        $startDate = Carbon::parse($fecha_inicio)->startOfDay();
        $endDate = Carbon::parse($fecha_fin)->endOfDay();

        $params = $this->validarParametros($request->merge([
            'fecha_inicio' => $fecha_inicio,
            'fecha_fin' => $fecha_fin,
        ]));
        $params['empresa_id'] = $empresaId;
        $notasPayload = $this->service->generar($params);

        $balance = app(BalanceGeneralNiifSvPresenter::class)->build($empresaId, $startDate, $endDate);
        $estado = app(EstadoResultadosNiifSvPresenter::class)->build($empresaId, $startDate, $endDate);
        $flujo = app(FlujoEfectivoHibridoNiifSvPresenter::class)->build($empresaId, $startDate, $endDate, false);
        $patrimonio = app(CambiosPatrimonioNiifSvPresenter::class)->build($empresaId, $startDate, $endDate, false, true);

        $pdf = PDF::loadView('reportes.contabilidad.estados_financieros_completos', compact(
            'empresa',
            'fecha_inicio',
            'fecha_fin',
            'balance',
            'estado',
            'flujo',
            'patrimonio',
            'notasPayload'
        ));
        $pdf->setPaper('US Letter', 'portrait');

        return $pdf->stream();
    }

    public function catalogo()
    {
        $this->autorizarVer();

        return response()->json([
            'notas' => NotasEstadosFinancierosCatalog::DEFINICIONES,
            'permisos' => [
                'ver' => NotasEstadosFinancierosPermisos::puedeVer(),
                'editar_auto' => NotasEstadosFinancierosPermisos::puedeEditarAuto(),
                'editar_manual' => NotasEstadosFinancierosPermisos::puedeEditarManual(),
                'emitir' => NotasEstadosFinancierosPermisos::puedeEmitir(),
            ],
        ], 200);
    }

    private function autorizarVer(): void
    {
        if (! NotasEstadosFinancierosPermisos::puedeVer()) {
            abort(403, 'No tiene permiso para ver las notas a los estados financieros.');
        }
    }

    /** @return array<string, mixed> */
    private function validarParametros(Request $request): array
    {
        $validated = $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
            'periodo_actual' => 'nullable|string|max:32',
            'fecha_aprobacion_junta' => 'nullable|date',
            'incluir_comparativo' => 'nullable|boolean',
            'periodo_anterior' => 'nullable|string|max:32',
            'nivel_detalle' => 'nullable|in:completo,resumido',
            'notas_a_incluir' => 'nullable|array',
            'notas_a_incluir.*' => 'integer|min:1|max:16',
            'configuracion' => 'nullable|array',
            'contenido_manual' => 'nullable|array',
        ]);

        $validated['notas_a_incluir'] = $validated['notas_a_incluir']
            ?? NotasEstadosFinancierosCatalog::notasPorDefecto();
        $validated['nivel_detalle'] = $validated['nivel_detalle'] ?? 'completo';
        $validated['periodo_actual'] = $validated['periodo_actual']
            ?? Carbon::parse($validated['fecha_fin'])->format('Y');

        return $validated;
    }
}

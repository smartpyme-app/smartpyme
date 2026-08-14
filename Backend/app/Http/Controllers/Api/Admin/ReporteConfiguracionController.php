<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerarReporteAutomaticoJob;
use App\Models\Admin\ReporteConfiguracion;
use App\Models\Admin\ReporteExportacion;
use App\Models\Admin\Sucursal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\Admin\ReporteConfiguracion\StoreReporteConfiguracionRequest;
use App\Http\Requests\Admin\ReporteConfiguracion\UpdateEstadoReporteConfiguracionRequest;
use App\Http\Requests\Admin\ReporteConfiguracion\EnviarPruebaReporteRequest;
use App\Http\Requests\Admin\ReporteConfiguracion\ExportarReporteRequest;
use App\Http\Requests\Admin\ReporteConfiguracion\ExportarPDFReporteRequest;

class ReporteConfiguracionController extends Controller
{
    public function index(Request $request)
    {
        $id_empresa = Auth::user()->id_empresa;
        $query = ReporteConfiguracion::where('id_empresa', $id_empresa);

        if ($request->has('buscador') && $request->buscador) {
            $query->where(function ($q) use ($request) {
                $q->where('tipo_reporte', 'like', '%' . $request->buscador . '%')
                    ->orWhere('frecuencia', 'like', '%' . $request->buscador . '%')
                    ->orWhere('asunto_correo', 'like', '%' . $request->buscador . '%');
            });
        }

        $orden = $request->has('orden') ? $request->orden : 'created_at';
        $direccion = $request->has('direccion') ? $request->direccion : 'desc';
        $query->orderBy($orden, $direccion);

        $paginate = $request->has('paginate') ? $request->paginate : 10;

        return $query->paginate($paginate);
    }

    public function store(StoreReporteConfiguracionRequest $request)
    {

        $datos = $request->all();
        $datos['id_empresa'] = Auth::user()->id_empresa;

        if (empty($datos['sucursales'])) {
            $datos['sucursales'] = Sucursal::where('id_empresa', Auth::user()->id_empresa)
                ->pluck('id')
                ->toArray();
        }

        $datos['sucursales'] = $this->normalizarSucursales($datos['sucursales']);

        if (isset($datos['activo']) && $datos['activo']) {
            if ($datos['tipo_reporte'] === 'ventas-por-categoria-vendedor') {
                $existeConfiguracionActiva = ReporteConfiguracion::where('id_empresa', Auth::user()->id_empresa)
                    ->where('tipo_reporte', $datos['tipo_reporte'])
                    ->where('activo', true);

                if (isset($datos['id']) && $datos['id']) {
                    $existeConfiguracionActiva->where('id', '!=', $datos['id']);
                }

                $configuracionesExistentes = $existeConfiguracionActiva->get();

                foreach ($configuracionesExistentes as $config) {
                    if ($this->sonSucursalesEquivalentes($datos['sucursales'], $config->sucursales)) {
                        $config->activo = false;
                        $config->save();
                    }
                }
            } else {
                $existeConfiguracionActiva = ReporteConfiguracion::where('id_empresa', Auth::user()->id_empresa)
                    ->where('tipo_reporte', $datos['tipo_reporte'])
                    ->where('activo', true);

                if (isset($datos['id']) && $datos['id']) {
                    $existeConfiguracionActiva->where('id', '!=', $datos['id']);
                }

                $configuracionExistente = $existeConfiguracionActiva->first();

                if ($configuracionExistente) {
                    $configuracionExistente->activo = false;
                    $configuracionExistente->save();
                }
            }
        }

        if (isset($datos['id']) && $datos['id']) {
            $configuracion = ReporteConfiguracion::findOrFail($datos['id']);
            $configuracion->update($datos);
        } else {
            $configuracion = ReporteConfiguracion::create($datos);
        }

        return response()->json($configuracion, 200);
    }

    public function show($id)
    {
        $configuracion = ReporteConfiguracion::findOrFail($id);

        if ($configuracion->id_empresa !== Auth::user()->id_empresa) {
            return response()->json(['error' => 'No tiene permiso para ver esta configuración'], 403);
        }

        return response()->json($configuracion, 200);
    }

    public function updateEstado(UpdateEstadoReporteConfiguracionRequest $request, $id)
    {
        $configuracion = ReporteConfiguracion::findOrFail($id);
        if ($configuracion->id_empresa !== Auth::user()->id_empresa) {
            return response()->json(['error' => 'No tiene permiso para modificar esta configuración'], 403);
        }

        if ($request->activo) {
            if ($configuracion->tipo_reporte === 'ventas-por-categoria-vendedor') {
                $configuracionesActivas = ReporteConfiguracion::where('id_empresa', Auth::user()->id_empresa)
                    ->where('tipo_reporte', $configuracion->tipo_reporte)
                    ->where('activo', true)
                    ->where('id', '!=', $id)
                    ->get();

                foreach ($configuracionesActivas as $configActiva) {
                    if ($this->sonSucursalesEquivalentes($configuracion->sucursales, $configActiva->sucursales)) {
                        $configActiva->activo = false;
                        $configActiva->save();
                    }
                }
            } else {
                $existeConfiguracionActiva = ReporteConfiguracion::where('id_empresa', Auth::user()->id_empresa)
                    ->where('tipo_reporte', $configuracion->tipo_reporte)
                    ->where('activo', true)
                    ->where('id', '!=', $id)
                    ->first();

                if ($existeConfiguracionActiva) {
                    $existeConfiguracionActiva->activo = false;
                    $existeConfiguracionActiva->save();
                }
            }
        }

        $configuracion->activo = $request->activo;
        $configuracion->save();

        return response()->json($configuracion, 200);
    }

    private function sonSucursalesEquivalentes($sucursales1, $sucursales2)
    {
        $sucursales1 = $this->normalizarSucursales($sucursales1);
        $sucursales2 = $this->normalizarSucursales($sucursales2);

        if (empty($sucursales1) && empty($sucursales2)) {
            return true;
        }
        $todasSucursales = Sucursal::where('id_empresa', Auth::user()->id_empresa)
            ->pluck('id')
            ->toArray();
        $todasSucursalesOrdenadas = collect($todasSucursales)->sort()->values()->toArray();

        $primeroEsTodas = !empty($sucursales1) && count($sucursales1) === count($todasSucursalesOrdenadas) &&
            empty(array_diff($sucursales1, $todasSucursalesOrdenadas));

        $segundoEsTodas = !empty($sucursales2) && count($sucursales2) === count($todasSucursalesOrdenadas) &&
            empty(array_diff($sucursales2, $todasSucursalesOrdenadas));

        if (($primeroEsTodas && empty($sucursales2)) || ($segundoEsTodas && empty($sucursales1))) {
            return true;
        }

        if ($primeroEsTodas && $segundoEsTodas) {
            return true;
        }
        return json_encode($sucursales1) === json_encode($sucursales2);
    }

    private function normalizarSucursales($sucursales)
    {
        if (is_array($sucursales)) {
            return collect($sucursales)->sort()->values()->toArray();
        } elseif (is_string($sucursales)) {
            try {
                return collect(json_decode($sucursales, true))->sort()->values()->toArray();
            } catch (\Exception $e) {
                return [];
            }
        }

        return [];
    }

    public function destroy($id)
    {
        $configuracion = ReporteConfiguracion::findOrFail($id);

        if ($configuracion->id_empresa !== Auth::user()->id_empresa) {
            return response()->json(['error' => 'No tiene permiso para eliminar esta configuración'], 403);
        }

        $configuracion->delete();

        return response()->json(['message' => 'Configuración eliminada correctamente'], 200);
    }

    public function enviarPrueba(EnviarPruebaReporteRequest $request)
    {
        $configuracion = ReporteConfiguracion::findOrFail($request->id_configuracion);

        if ($configuracion->id_empresa !== Auth::user()->id_empresa) {
            return response()->json(['error' => 'No tiene permiso para usar esta configuración'], 403);
        }

        $sucursales = $request->has('sucursales') && is_array($request->sucursales)
            ? $this->normalizarSucursales($request->sucursales)
            : $this->normalizarSucursales($configuracion->sucursales);

        $destinatarios = $request->email_prueba
            ? [$request->email_prueba]
            : ($configuracion->destinatarios ?? []);

        if (empty($destinatarios)) {
            return response()->json(['error' => 'Debe indicar al menos un destinatario'], 422);
        }

        $exportacion = $this->encolarExportacion(
            $configuracion,
            $request->fecha_inicio,
            $request->fecha_fin,
            $sucursales,
            ReporteExportacion::MODO_EMAIL,
            ReporteExportacion::FORMATO_EXCEL,
            $destinatarios
        );

        return response()->json([
            'id' => $exportacion->id,
            'estado' => $exportacion->estado,
            'message' => 'El reporte se está generando y se enviará al correo cuando esté listo.',
        ], 202);
    }

    public function exportar(ExportarReporteRequest $request)
    {
        return $this->encolarDescarga($request, ReporteExportacion::FORMATO_EXCEL);
    }

    public function exportarPDF(ExportarPDFReporteRequest $request)
    {
        return $this->encolarDescarga($request, ReporteExportacion::FORMATO_PDF);
    }

    public function estadoExportacion($id)
    {
        $exportacion = ReporteExportacion::findOrFail($id);

        if ($exportacion->id_empresa !== Auth::user()->id_empresa) {
            return response()->json(['error' => 'No tiene permiso para ver esta exportación'], 403);
        }

        return response()->json([
            'id' => $exportacion->id,
            'estado' => $exportacion->estado,
            'formato' => $exportacion->formato,
            'nombre_archivo' => $exportacion->nombre_archivo,
            'error' => $exportacion->error,
        ]);
    }

    public function descargarExportacion($id)
    {
        $exportacion = ReporteExportacion::findOrFail($id);

        if ($exportacion->id_empresa !== Auth::user()->id_empresa) {
            return response()->json(['error' => 'No tiene permiso para descargar esta exportación'], 403);
        }

        if ($exportacion->modo !== ReporteExportacion::MODO_DOWNLOAD) {
            return response()->json(['error' => 'Esta exportación no es descargable'], 422);
        }

        if ($exportacion->estado !== ReporteExportacion::ESTADO_DONE) {
            return response()->json([
                'error' => 'El archivo aún no está listo',
                'estado' => $exportacion->estado,
            ], 409);
        }

        $path = $exportacion->absolutePath();
        if (!$path || !file_exists($path)) {
            return response()->json(['error' => 'Archivo no encontrado'], 404);
        }

        $nombre = $exportacion->nombre_archivo ?: basename($path);
        $mime = $this->mimeFromNombre($nombre);

        return response()->download($path, $nombre, [
            'Content-Type' => $mime,
        ]);
    }

    private function encolarDescarga(Request $request, string $formato)
    {
        $configuracion = ReporteConfiguracion::findOrFail($request->id);

        if ($configuracion->id_empresa !== Auth::user()->id_empresa) {
            return response()->json(['error' => 'No tiene permiso para usar esta configuración'], 403);
        }

        if ($formato === ReporteExportacion::FORMATO_PDF) {
            $soportados = ['ventas-por-categoria-vendedor', 'detalle-ventas-vendedor'];
            if (!in_array($configuracion->tipo_reporte, $soportados, true)) {
                return response()->json(['error' => 'Formato PDF no disponible para este tipo de reporte'], 422);
            }
        }

        $sucursales = $request->has('sucursales') && is_array($request->sucursales)
            ? $this->normalizarSucursales($request->sucursales)
            : $this->normalizarSucursales($configuracion->sucursales);

        $exportacion = $this->encolarExportacion(
            $configuracion,
            $request->fecha_inicio,
            $request->fecha_fin,
            $sucursales,
            ReporteExportacion::MODO_DOWNLOAD,
            $formato
        );

        return response()->json([
            'id' => $exportacion->id,
            'estado' => $exportacion->estado,
            'message' => 'El reporte se está generando. Consulte el estado para descargarlo.',
        ], 202);
    }

    private function encolarExportacion(
        ReporteConfiguracion $configuracion,
        string $fechaInicio,
        string $fechaFin,
        array $sucursales,
        string $modo,
        string $formato,
        ?array $destinatarios = null
    ): ReporteExportacion {
        $exportacion = ReporteExportacion::create([
            'id_empresa' => $configuracion->id_empresa,
            'id_usuario' => Auth::id(),
            'id_configuracion' => $configuracion->id,
            'modo' => $modo,
            'formato' => $formato,
            'estado' => ReporteExportacion::ESTADO_PENDING,
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'sucursales' => $sucursales,
            'destinatarios' => $destinatarios,
        ]);

        GenerarReporteAutomaticoJob::dispatch($exportacion->id);

        return $exportacion;
    }

    private function mimeFromNombre(string $nombre): string
    {
        $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
        if ($ext === 'zip') {
            return 'application/zip';
        }
        if ($ext === 'pdf') {
            return 'application/pdf';
        }

        return 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    }
}

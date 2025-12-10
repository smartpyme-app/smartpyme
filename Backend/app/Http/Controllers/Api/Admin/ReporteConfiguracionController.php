<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exports\ReportesAutomaticos\DetalleVentasPorVendedor\DetalleVentasVendedorPdfExport;
use App\Exports\ReportesAutomaticos\VentasPorCategoriaPorVendedor\VentasPorCategoriaVendedorPdfExport;
use App\Http\Controllers\Api\Ventas\VentasController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\InventarioController;
use App\Models\Admin\ReporteConfiguracion;
use App\Models\Admin\Sucursal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
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

        // Ordenamiento
        $orden = $request->has('orden') ? $request->orden : 'created_at';
        $direccion = $request->has('direccion') ? $request->direccion : 'desc';
        $query->orderBy($orden, $direccion);

        // Paginación
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

                // Verificar si hay alguna configuración con sucursales equivalentes
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
        }
  
        else if (is_string($sucursales)) {
            try {
                return collect(json_decode($sucursales, true))->sort()->values()->toArray();
            } catch (\Exception $e) {
                return [];
            }
        }
 
        else {
            return [];
        }
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

        $fecha_inicio = $request->fecha_inicio;
        $fecha_fin = $request->fecha_fin;

        try {
            switch ($configuracion->tipo_reporte) {
                case 'ventas-por-vendedor':
                    $controller = new VentasController();


                    $destinatarios = $request->email_prueba
                        ? [$request->email_prueba]
                        : $configuracion->destinatarios;


                    $resultado = $controller->enviarReporteProgramadoTest($configuracion, $destinatarios, $fecha_inicio, $fecha_fin);
                    return response()->json(['message' => 'Reporte enviado correctamente'], 200);
                case 'ventas-por-categoria-vendedor':
                    $controller = new VentasController();

                    $destinatarios = $request->email_prueba
                        ? [$request->email_prueba]
                        : $configuracion->destinatarios;

                    $resultado = $controller->enviarReporteProgramadoTest($configuracion, $destinatarios, $fecha_inicio, $fecha_fin);

                    return response()->json(['message' => 'Reporte enviado correctamente'], 200);
                case 'estado-financiero-consolidado-sucursales':
                    $controller = new VentasController();

                    $destinatarios = $request->email_prueba
                        ? [$request->email_prueba]
                        : $configuracion->destinatarios;

                    $resultado = $controller->enviarReporteProgramadoTest($configuracion, $destinatarios, $fecha_inicio, $fecha_fin);

                    return response()->json(['message' => 'Reporte enviado correctamente'], 200);


                case 'detalle-ventas-vendedor':
                    $controller = new VentasController();

                    $destinatarios = $request->email_prueba
                        ? [$request->email_prueba]
                        : $configuracion->destinatarios;

                    $resultado = $controller->enviarReporteProgramadoTest($configuracion, $destinatarios, $fecha_inicio, $fecha_fin);
                    return response()->json(['message' => 'Reporte enviado correctamente'], 200);
                case 'inventario-por-sucursal':
                    $controller = new VentasController();
                        
                    $destinatarios = $request->email_prueba
                    ? [$request->email_prueba]
                    : $configuracion->destinatarios;

                    $resultado = $controller->enviarReporteProgramadoTest($configuracion, $destinatarios, $fecha_inicio, $fecha_fin);
                    return response()->json(['message' => 'Reporte enviado correctamente'], 200);
                case 'ventas-por-utilidades':
                    $controller = new VentasController();

                    $destinatarios = $request->email_prueba
                        ? [$request->email_prueba]
                        : $configuracion->destinatarios;

                    $resultado = $controller->enviarReporteProgramadoTest($configuracion, $destinatarios, $fecha_inicio, $fecha_fin);
                    return response()->json(['message' => 'Reporte enviado correctamente'], 200);
                default:
                    return response()->json(['error' => 'Tipo de reporte no implementado'], 422);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al enviar el reporte: ' . $e->getMessage()], 500);
        }
    }

    public function exportar(ExportarReporteRequest $request)
    {
        $configuracion = ReporteConfiguracion::findOrFail($request->id);

        if ($configuracion->id_empresa !== Auth::user()->id_empresa) {
            return response()->json(['error' => 'No tiene permiso para usar esta configuración'], 403);
        }

        $fecha_inicio = $request->fecha_inicio;
        $fecha_fin = $request->fecha_fin;

        try {
            switch ($configuracion->tipo_reporte) {
                case 'ventas-por-vendedor':
                    $controller = new VentasController();
                    $resultado = $controller->exportarReporteProgramado($configuracion, $fecha_inicio, $fecha_fin);
                    return $resultado;
                case 'ventas-por-categoria-vendedor':
                    $controller = new VentasController();
                    $resultado = $controller->exportarReporteProgramado($configuracion, $fecha_inicio, $fecha_fin);
                    return $resultado;
                case 'estado-financiero-consolidado-sucursales':
                    $controller = new VentasController();
                    $resultado = $controller->exportarReporteProgramado($configuracion, $fecha_inicio, $fecha_fin);
                    return $resultado;
                case 'detalle-ventas-vendedor':
                    $controller = new VentasController();
                    $resultado = $controller->exportarReporteProgramado($configuracion, $fecha_inicio, $fecha_fin);
                    return $resultado;
                case 'inventario-por-sucursal':
                    $controller = new InventarioController();
                    $resultado = $controller->exportarReporteProgramado($configuracion, $fecha_inicio, $fecha_fin);
                    return $resultado;
                case 'ventas-por-utilidades':
                    $controller = new VentasController();
                    $resultado = $controller->exportarReporteProgramado($configuracion, $fecha_inicio, $fecha_fin);
                    return $resultado;
                default:
                    return response()->json(['error' => 'Tipo de reporte no implementado'], 422);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al exportar el reporte: ' . $e->getMessage()], 500);
        }
    }

    public function exportarPDF(ExportarPDFReporteRequest $request)
    {
        // Iniciar el registro de logs
        Log::info('Iniciando exportación de PDF', [
            'request_data' => $request->all()
        ]);
        
        try {
            // Datos ya validados por el FormRequest
            $validatedData = $request->validated();
            
            Log::info('Datos validados correctamente', [
                'validatedData' => $validatedData
            ]);
            
            try {
                // Buscar la configuración
                $configuracion = ReporteConfiguracion::findOrFail($validatedData['id']);
                Log::info('Configuración encontrada', [
                    'id' => $configuracion->id,
                    'tipo_reporte' => $configuracion->tipo_reporte,
                    'id_empresa' => $configuracion->id_empresa
                ]);
                
                // Actualizar sucursales si se proporcionaron
                if (isset($validatedData['sucursales'])) {
                    $configuracion->sucursales = $validatedData['sucursales'];
                    Log::info('Sucursales actualizadas', [
                        'sucursales' => $configuracion->sucursales
                    ]);
                }
        
                // Verificar si existe un exportador PDF para este tipo de reporte
                Log::info('Preparando exportador para tipo de reporte', [
                    'tipo_reporte' => $configuracion->tipo_reporte
                ]);
                
                switch ($configuracion->tipo_reporte) {
                    case 'ventas-por-categoria-vendedor':
                        Log::info('Creando exportador VentasPorCategoriaVendedorPdfExport');
                        $exporter = new VentasPorCategoriaVendedorPdfExport(
                            $validatedData['fecha_inicio'],
                            $validatedData['fecha_fin'],
                            $configuracion->id_empresa,
                            $configuracion,
                            $configuracion->sucursales
                        );
                        Log::info('Exportador creado, iniciando download()');
                        try {
                            $response = $exporter->download();
                            Log::info('Download completado exitosamente');
                            return $response;
                        } catch (\Exception $e) {
                            Log::error('Error en el método download() de VentasPorCategoriaVendedorPdfExport', [
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString()
                            ]);
                            throw $e; // Re-lanzar para ser capturado por el catch exterior
                        }
                        
                    case 'detalle-ventas-vendedor':
                        Log::info('Creando exportador DetalleVentasVendedorPdfExport');
                        $exporter = new DetalleVentasVendedorPdfExport(
                            $validatedData['fecha_inicio'],
                            $validatedData['fecha_fin'],
                            $configuracion->id_empresa,
                            $configuracion,
                            $configuracion->sucursales
                        );
                        Log::info('Exportador creado, iniciando download()');
                        try {
                            $response = $exporter->download();
                            Log::info('Download completado exitosamente');
                            return $response;
                        } catch (\Exception $e) {
                            Log::error('Error en el método download() de DetalleVentasVendedorPdfExport', [
                                'error' => $e->getMessage(),
                                'file' => $e->getFile(),
                                'line' => $e->getLine(),
                                'trace' => $e->getTraceAsString()
                            ]);
                            throw $e; // Re-lanzar para ser capturado por el catch exterior
                        }
                        
                    default:
                        Log::warning('Formato PDF no disponible para el tipo de reporte', [
                            'tipo_reporte' => $configuracion->tipo_reporte
                        ]);
                        return response()->json(['error' => 'Formato PDF no disponible para este tipo de reporte'], 422);
                }
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                Log::error('Configuración de reporte no encontrada', [
                    'id' => $validatedData['id'] ?? null,
                    'error' => $e->getMessage()
                ]);
                return response()->json(['error' => 'La configuración de reporte solicitada no existe'], 404);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Error de validación en la solicitud', [
                'errors' => $e->errors(),
                'request_data' => $request->all()
            ]);
            return response()->json(['error' => 'Datos de solicitud inválidos', 'details' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Error no controlado al exportar reporte PDF', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);
            
            return response()->json(['error' => 'Error al generar el PDF: ' . $e->getMessage()], 500);
        }
    }
}

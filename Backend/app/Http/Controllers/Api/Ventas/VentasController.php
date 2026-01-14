<?php

namespace App\Http\Controllers\Api\Ventas;

use App\Http\Controllers\Controller;
use App\Models\Ventas\Venta;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\Ventas\StoreVentaRequest;
use App\Http\Requests\Ventas\FacturacionRequest;
use App\Http\Requests\Ventas\FacturacionConsignaRequest;
use App\Http\Requests\Ventas\IndexVentaRequest;
use App\Http\Requests\Ventas\LibroIvaRequest;
use App\Http\Requests\Ventas\HistorialVentaRequest;
use App\Services\Ventas\VentaService;
use App\Services\Ventas\InventarioService;
use App\Services\Ventas\VentaQueryService;
use App\Services\Ventas\AbonoService;
use App\Services\Ventas\DocumentoService;
use App\Services\Ventas\ReporteService;
use App\Services\Ventas\FacturacionConsignaService;
use App\Services\Ventas\LibroIvaService;
use App\Services\Ventas\CorteService;
use App\Services\Ventas\CxcService;
use App\Services\Ventas\HistorialService;
use App\Services\Ventas\ReporteEmailService;
use App\Services\Ventas\CotizacionService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use App\Models\Admin\Empresa;
use App\Models\Admin\Documento;

class VentasController extends Controller
{
    protected $ventaService;
    protected $inventarioService;
    protected $ventaQueryService;
    protected $abonoService;
    protected $documentoService;
    protected $reporteService;
    protected $facturacionConsignaService;
    protected $libroIvaService;
    protected $corteService;
    protected $cxcService;
    protected $historialService;
    protected $reporteEmailService;
    protected $cotizacionService;

    public function __construct(
        VentaService $ventaService,
        InventarioService $inventarioService,
        VentaQueryService $ventaQueryService,
        AbonoService $abonoService,
        DocumentoService $documentoService,
        ReporteService $reporteService,
        FacturacionConsignaService $facturacionConsignaService,
        LibroIvaService $libroIvaService,
        CorteService $corteService,
        CxcService $cxcService,
        HistorialService $historialService,
        ReporteEmailService $reporteEmailService,
        CotizacionService $cotizacionService
    ) {
        $this->ventaService = $ventaService;
        $this->inventarioService = $inventarioService;
        $this->ventaQueryService = $ventaQueryService;
        $this->abonoService = $abonoService;
        $this->documentoService = $documentoService;
        $this->reporteService = $reporteService;
        $this->facturacionConsignaService = $facturacionConsignaService;
        $this->libroIvaService = $libroIvaService;
        $this->corteService = $corteService;
        $this->cxcService = $cxcService;
        $this->historialService = $historialService;
        $this->reporteEmailService = $reporteEmailService;
        $this->cotizacionService = $cotizacionService;
    }

    public function index(IndexVentaRequest $request)
    {
        $filtros = $request->validated();
        $ventas = $this->ventaQueryService->obtenerVentasPaginadas($filtros);

        foreach ($ventas as $venta) {
            $venta->saldo = $venta->saldo;
        }

        return response()->json($ventas, 200);
    }

    public function read($id)
    {
        $venta = Venta::where('id', $id)
            ->with('devoluciones', 'detalles.composiciones', 'detalles.vendedor', 'detalles.producto', 'abonos.usuario', 'cliente', 'impuestos.impuesto', 'metodos_de_pago', 'vendedor', 'usuario', 'sucursal', 'documento', 'proyecto')
            ->firstOrFail();

        $venta->saldo = $venta->saldo;

        return response()->json($venta, 200);
    }

    public function store(StoreVentaRequest $request)
    {
        $request->validate([
            'id' => 'required|numeric',
            'fecha' => 'required',
            'estado' => 'required',
            'id_usuario' => 'required',
        ]);

        // Buscar la venta respetando el scope global de empresa
        $venta = Venta::where('id', $request->id)->with('detalles')->first();

        if (!$venta) {
            return response()->json(['error' => 'No se encontro ningun registro.', 'code' => 404], 404);
        }

        // Ajustar stocks
        foreach ($venta->detalles as $detalle) {

            $producto = Producto::where('id', $detalle->id_producto)
                ->with('composiciones')->firstOrFail();

            DB::beginTransaction();
            try {
                // Anular venta y regresar stock
                if (($venta->estado != 'Anulada') && ($request->estado == 'Anulada')) {
                    $this->inventarioService->revertirInventarioAnulacion($venta);
                    $this->abonoService->cancelarAbonos($venta);
                }

                // Cancelar anulación de venta y descargar stock
                if (($venta->estado == 'Anulada') && ($request->estado != 'Anulada')) {
                    $this->inventarioService->aplicarInventarioCancelacionAnulacion($venta);
                    $this->abonoService->confirmarAbonos($venta);

                    // Inventario compuestos
                    foreach ($detalle->composiciones()->get() as $comp) {

                        $inventario = Inventario::where('id_producto', $comp->id_producto)
                            ->where('id_bodega', $venta->id_bodega)->first();

                        if ($inventario) {
                            $inventario->stock -= $detalle->cantidad * $comp->cantidad;
                            $inventario->save();
                            $inventario->kardex($venta, ($detalle->cantidad * $comp->cantidad));
                        }
                    }

                    // Abonos
                    foreach ($venta->abonos as $abono) {
                        $abono->estado = 'Confirmado';
                        $abono->save();
                    }
                }

                // El frontend ya envía el total sin propina, así que no necesitamos ajustarlo
                $venta->fill($request->all());
                $venta->save();

                DB::commit();
                return response()->json($venta, 200);
            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json(['error' => $e->getMessage()], 400);
            }
        }
    }

    public function delete($id)
    {
        $venta = $this->ventaService->eliminarVenta($id);
        return response()->json($venta, 201);
    }

    public function corte()
    {
        $ventas = $this->corteService->obtenerVentasCorte();
        return response()->json($ventas, 200);
    }

    public function facturacion(FacturacionRequest $request)
    {
        // Validar que usuarios "Ventas Limitado" no puedan crear ventas al crédito
        $user = auth()->user();
        if ($user->tipo === 'Ventas Limitado' && $request->credito == 1) {
            return response()->json([
                'error' => 'Los usuarios de tipo "Ventas Limitado" no pueden crear ventas al crédito.'
            ], 403);
        }

        DB::beginTransaction();
        try {

            // Obtener la empresa para verificar configuración de vender sin stock
            $empresa = Empresa::findOrFail(Auth::user()->id_empresa);
            $puedeVenderSinStock = $empresa->vender_sin_stock == 1;

            // Validar y obtener datos
            $data = $request->validated();

            // Si es cotización, usar el servicio de cotizaciones
            if ($request->cotizacion == 1) {
                // Crear o actualizar cotización
                $cotizacion = $this->cotizacionService->crearOActualizarCotizacion($data);

                // Asignar correlativo
                $this->cotizacionService->asignarCorrelativo($cotizacion, $data['id_documento']);

                // Guardar detalles
                $this->cotizacionService->guardarDetalles($cotizacion, $data['detalles']);

                DB::commit();
                return response()->json($cotizacion, 200);
            }

            // Si NO es cotización, guardar en ventas (flujo original)
            // Crear o actualizar venta
            $venta = $this->ventaService->crearOActualizarVenta($data);

            // Asignar correlativo
            $this->ventaService->asignarCorrelativo($venta, $data['id_documento']);

            // Guardar detalles
            $this->ventaService->guardarDetalles($venta, $data['detalles']);

            // Actualizar inventario si no es cotización
            if ($request->cotizacion == 0) {
                $this->inventarioService->actualizarInventarioVenta($venta, $data['detalles'], false);
            }

            // Procesar evento si existe
            $this->ventaService->procesarEvento($request->id_evento ?? null, $venta);

            // Procesar proyecto si existe
            $this->ventaService->procesarProyecto($request->id_proyecto ?? null, $venta);

            // Guardar impuestos
            $this->ventaService->guardarImpuestos($venta, $request->impuestos ?? null);

            // Guardar métodos de pago
            $this->ventaService->guardarMetodosDePago($venta, $request->metodos_de_pago ?? null);

            DB::commit();
            return response()->json($venta, 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error en facturacion: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error en facturacion: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function facturacionConsigna(FacturacionConsignaRequest $request)
    {
        // Validar que usuarios "Ventas Limitado" no puedan crear ventas al crédito
        $user = auth()->user();
        if ($user->tipo === 'Ventas Limitado') {
            return response()->json([
                'error' => 'Los usuarios de tipo "Ventas Limitado" no pueden crear ventas al crédito.'
            ], 403);
        }

        DB::beginTransaction();
        try {
            $venta = $this->facturacionConsignaService->procesarConsigna($request->validated());

            DB::commit();
            return response()->json($venta, 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error en facturacion consigna: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error en facturacion consigna: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function pendientes()
    {
        $ventas = $this->corteService->obtenerVentasPendientes();
        return response()->json($ventas, 200);
    }

    public function vendedor()
    {
        $ventas = $this->corteService->obtenerVentasVendedor();
        return response()->json($ventas, 200);
    }

    public function generarDoc($id)
    {
        return $this->documentoService->generarDocumento($id);
    }

    public function anularDoc()
    {
        return view('reportes.anulacion');
    }

    public function sinDevolucion()
    {
        $ventas = $this->historialService->obtenerVentasSinDevolucion();
        return response()->json($ventas, 200);
    }

    public function libroIva(LibroIvaRequest $request)
    {
        $ivas = $this->libroIvaService->generarLibroIva(
            $request->inicio,
            $request->fin,
            $request->tipo_documento
        );

        return response()->json($ivas, 200);
    }

    public function cxc()
    {
        $cobros = $this->cxcService->obtenerCxc();
        return response()->json($cobros, 200);
    }

    public function cxcBuscar($txt)
    {
        $cobros = $this->cxcService->buscarCxc($txt);
        return response()->json($cobros, 200);
    }

    public function historial(HistorialVentaRequest $request)
    {
        $movimientos = $this->historialService->obtenerHistorial($request->inicio, $request->fin);
        return response()->json($movimientos, 200);
    }

    public function export(IndexVentaRequest $request)
    {
        return $this->reporteService->exportarVentas($request->validated());
    }

    public function exportDetalles(IndexVentaRequest $request)
    {
        return $this->reporteService->exportarDetallesVentas($request->validated());
    }

    public function reporteDiario(IndexVentaRequest $request)
    {
        try {
            $opciones = [];
            if ($request->has('enviar_correo')) {
                $opciones['enviar_correo'] = true;
            }

            return $this->reporteService->generarReporteDiario($opciones);
        } catch (\Exception $e) {
            Log::error("Error al generar reporte diario: " . $e->getMessage());
            throw $e;
        }
    }

    public function acumuladoExport(IndexVentaRequest $request)
    {
        $user = JWTAuth::parseToken()->authenticate();
        $filtros = $request->validated();
        $filtros['id_empresa'] = $user->id_empresa;

        return $this->reporteService->exportarAcumulado($filtros);
    }

    public function porMarcasExport(IndexVentaRequest $request)
    {
        return $this->reporteService->exportarPorMarcas($request->validated());
    }

    public function porUtilidadesExport(IndexVentaRequest $request)
    {
        return $this->reporteService->exportarPorUtilidades($request->validated());
    }

    public function enviarReporteDiario()
    {
        try {
            $resultado = $this->reporteEmailService->enviarReporteDiario();
            return response()->json($resultado, 200);
        } catch (\Exception $e) {
            Log::error('Error al enviar reporte diario: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function enviarReporteProgramado($configuracion, $empresa, $fechaInicio, $fechaFin)
    {
        try {
            // $fecha = Carbon::today()->format('Y-m-d');
            if ($configuracion->tipo_reporte === 'ventas-por-vendedor') {
                $export = new VentasPorVendedorExport($fechaInicio, $fechaFin, $empresa->id);
            } elseif ($configuracion->tipo_reporte === 'ventas-por-categoria-vendedor') {
                $export = new VentasPorCategoriaVendedorExport($fechaInicio, $fechaFin, $empresa->id, $configuracion);
            } elseif ($configuracion->tipo_reporte === 'estado-financiero-consolidado-sucursales') {
                $export = new EstadoFinancieroConsolidadoSucursalesExport($fechaInicio, $fechaFin, $empresa->id);
            } elseif ($configuracion->tipo_reporte === 'detalle-ventas-vendedor') {
                $export = new DetalleVentasVendedorExport($fechaInicio, $fechaFin, $empresa->id, $configuracion->sucursales);
            } elseif ($configuracion->tipo_reporte === 'inventario-por-sucursal') {
                $export = new InventarioExport($fechaInicio, $fechaFin, $empresa->id, $configuracion);
            } elseif ($configuracion->tipo_reporte === 'ventas-por-utilidades') {
                $request = new Request([
                    'id_empresa' => $empresa->id,
                    'inicio' => $fechaInicio,
                    'fin' => $fechaFin,
                    'sucursales' => $configuracion->sucursales ?? [],
                ]);
                $export = new VentasPorUtilidadesExport();
                $export->filter($request);
            }
            $filename = "{$configuracion->tipo_reporte}-{$fechaInicio}.xlsx";


            $relativePath = "reportes/{$filename}";
            $empresa = Empresa::find($empresa->id);


            $directory = public_path('img/reportes');
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }

            Storage::disk('public')->put($relativePath, '');

            Excel::store($export, $relativePath, 'public');

            $filePath = public_path('img/' . $relativePath);

            if (!file_exists($filePath)) {
                Log::error("Archivo no encontrado en: {$filePath}");
                $alternativePath = storage_path('app/public/' . $relativePath);
                Log::info("Intentando ruta alternativa: {$alternativePath}");

                if (file_exists($alternativePath)) {
                    $filePath = $alternativePath;
                } else {
                    throw new \Exception("El archivo no fue generado correctamente. No se encuentra en ninguna de las rutas esperadas.");
                }
            }

            if ($configuracion->tipo_reporte === 'ventas-por-vendedor') {
                $ventasDelDia = Venta::whereBetween('fecha', [$fechaInicio, $fechaFin])
                    ->where('id_empresa', $empresa->id)
                    ->where('cotizacion', 0)
                    ->where('estado', '!=', 'Anulada')
                    ->count();

                $totalVentas = Venta::whereBetween('fecha', [$fechaInicio, $fechaFin])
                    ->where('id_empresa', $empresa->id)
                    ->where('cotizacion', 0)
                    ->where('estado', '!=', 'Anulada')
                    ->sum('total');

                $vendedoresConVentas = Venta::whereBetween('fecha', [$fechaInicio, $fechaFin])
                    ->where('id_empresa', $empresa->id)
                    ->where('cotizacion', 0)
                    ->distinct('id_vendedor')
                    ->where('estado', '!=', 'Anulada')
                    ->count('id_vendedor');
            } else {
                $ventasDelDia = 0;
                $totalVentas = 0;
                $vendedoresConVentas = 0;
            }

            $asuntos_correos = [
                'ventas-por-vendedor' => 'Reporte de Ventas por Vendedor ' . $fechaInicio . ' al ' . $fechaFin,
                'ventas-por-categoria-vendedor' => 'Reporte de Ventas por Categoría y Vendedor ' . $fechaInicio . ' al ' . $fechaFin,
                'estado-financiero-consolidado-sucursales' => 'Reporte de Estado Financiero Consolidado por Sucursales ' . $fechaInicio . ' al ' . $fechaFin,
                'detalle-ventas-vendedor' => 'Reporte de Detalle de Ventas por Vendedor ' . $fechaInicio . ' al ' . $fechaFin,
                'inventario-por-sucursal' => 'Reporte de Inventario por Sucursal ' . $fechaInicio . ' al ' . $fechaFin,
            ];

            $asunto = $asuntos_correos[$configuracion->tipo_reporte] ?? $configuracion->asunto_correo;



            $datos = [
                'fecha' => $fechaInicio,
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
                'ventasDelDia' => $ventasDelDia,
                'totalVentas' => $totalVentas,
                'vendedoresConVentas' => $vendedoresConVentas,
                'archivoPath' => $filePath,
                'nombreArchivo' => basename($filePath),
                'asunto' => $asunto,
                'automatico' => true,
                'tipo_reporte' => $configuracion->tipo_reporte,
                'empresa' => $empresa->nombre
            ];

            $destinatarios = $configuracion->destinatarios;

            Mail::to($destinatarios)->send(new ReporteVentasPorVendedor($datos));

            // Registrar que se envió el reporte
            Log::info("Reporte enviado: {$configuracion->tipo_reporte}", [
                'configuracion_id' => $configuracion->id,
                'destinatarios' => $destinatarios,
                'fecha' => $fechaInicio . ' al ' . $fechaFin
            ]);


            unlink($filePath);


            return true;
        } catch (\Exception $e) {
            Log::error('Error al enviar reporte programado: ' . $e->getMessage(), [
                'configuracion_id' => $configuracion->id ?? null,
                'tipo_reporte' => $configuracion->tipo_reporte ?? null
            ]);
            throw $e;
        }
    }

    public function enviarReporteProgramadoTest($configuracion, $destinatarios, $fechaInicio, $fechaFin)
    {
        try {
            if ($configuracion->tipo_reporte === 'ventas-por-vendedor') {
                $export = new VentasPorVendedorExport($fechaInicio, $fechaFin, $configuracion->id_empresa);
                $filename = "ventas-por-vendedor-prueba-{$fechaInicio}-{$fechaFin}-" . time() . ".xlsx";
            } elseif ($configuracion->tipo_reporte === 'ventas-por-categoria-vendedor') {
                $export = new VentasPorCategoriaVendedorExport($fechaInicio, $fechaFin, $configuracion->id_empresa, $configuracion);
                $filename = "ventas-por-categoria-vendedor-prueba-{$fechaInicio}-{$fechaFin}-" . time() . ".xlsx";
            } elseif ($configuracion->tipo_reporte === 'estado-financiero-consolidado-sucursales') {
                $export = new EstadoFinancieroConsolidadoSucursalesExport($fechaInicio, $fechaFin, $configuracion->id_empresa, $configuracion);
                $filename = "estado-financiero-consolidado-sucursales-prueba-{$fechaInicio}-{$fechaFin}-" . time() . ".xlsx";
            } elseif ($configuracion->tipo_reporte === 'detalle-ventas-vendedor') {
                $export = new DetalleVentasVendedorExport($fechaInicio, $fechaFin, $configuracion->id_empresa, $configuracion->sucursales);
                $filename = "detalle-ventas-vendedor-prueba-{$fechaInicio}-{$fechaFin}-" . time() . ".xlsx";
            } elseif ($configuracion->tipo_reporte === 'inventario-por-sucursal') {
                $export = new InventarioExport($fechaInicio, $fechaFin, $configuracion->id_empresa, $configuracion);
                $filename = "inventario-por-sucursal-prueba-{$fechaInicio}-{$fechaFin}-" . time() . ".xlsx";
            } elseif ($configuracion->tipo_reporte === 'ventas-por-utilidades') {
                $request = new Request([
                    'id_empresa' => $configuracion->id_empresa,
                    'inicio' => $fechaInicio,
                    'fin' => $fechaFin,
                    'sucursales' => $configuracion->sucursales ?? [],
                ]);
                $export = new VentasPorUtilidadesExport();
                $export->filter($request);
                $filename = "ventas-por-utilidades-prueba-{$fechaInicio}-{$fechaFin}-" . time() . ".xlsx";
            }

            $relativePath = "reportes/{$filename}";
            $empresa = Empresa::find($configuracion->id_empresa);

            $directory = public_path('img/reportes');
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }


            Storage::disk('public')->put($relativePath, '');

            Excel::store($export, $relativePath, 'public');


            $filePath = public_path('img/' . $relativePath);


            if (!file_exists($filePath)) {

                Log::error("Archivo no encontrado en: {$filePath}");

                $alternativePath = storage_path('app/public/' . $relativePath);
                Log::info("Intentando ruta alternativa: {$alternativePath}");

                if (file_exists($alternativePath)) {
                    $filePath = $alternativePath;
                } else {
                    throw new \Exception("El archivo no fue generado correctamente. No se encuentra en ninguna de las rutas esperadas.");
                }
            }

            // Obtener estadísticas para incluir en el correo
            if($configuracion->tipo_reporte === 'ventas-por-vendedor') {
                $ventasDelDia = Venta::whereBetween('fecha', [$fechaInicio, $fechaFin])
                    ->where('cotizacion', 0)
                    ->where('estado', '!=', 'Anulada')
                    ->count();

                $totalVentas = Venta::whereBetween('fecha', [$fechaInicio, $fechaFin])
                    ->where('cotizacion', 0)
                    ->where('estado', '!=', 'Anulada')
                    ->sum('total');

                $vendedoresConVentas = Venta::whereBetween('fecha', [$fechaInicio, $fechaFin])
                    ->where('cotizacion', 0)
                    ->distinct('id_vendedor')
                    ->where('estado', '!=', 'Anulada')
                    ->count('id_vendedor');
            }else{
                $ventasDelDia = 0;
                $totalVentas = 0;
                $vendedoresConVentas = 0;
            }

            $asuntos_correos = [
                'ventas-por-vendedor' => 'Reporte de Ventas por Vendedor ' . $fechaInicio . ' al ' . $fechaFin,
                'ventas-por-categoria-vendedor' => 'Reporte de Ventas por Categoría y Vendedor ' . $fechaInicio . ' al ' . $fechaFin,
                'estado-financiero-consolidado-sucursales' => 'Reporte de Estado Financiero Consolidado por Sucursales ' . $fechaInicio . ' al ' . $fechaFin,
                'detalle-ventas-vendedor' => 'Reporte de Detalle de Ventas por Vendedor ' . $fechaInicio . ' al ' . $fechaFin,
                'inventario-por-sucursal' => 'Reporte de Inventario por Sucursal ' . $fechaInicio . ' al ' . $fechaFin,
                'ventas-por-utilidades' => 'Reporte de Ventas por Utilidades ' . $fechaInicio . ' al ' . $fechaFin,
            ];

            $asunto = $asuntos_correos[$configuracion->tipo_reporte] ?? $configuracion->asunto_correo;

            $datos = [
                'fecha' => Carbon::today()->format('d/m/Y'),
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
                'ventasDelDia' => $ventasDelDia,
                'totalVentas' => $totalVentas,
                'vendedoresConVentas' => $vendedoresConVentas,
                'archivoPath' => $filePath,
                'nombreArchivo' => basename($filePath),
                'asunto' => $asunto ?: "Reporte de Prueba: " . $configuracion->tipo_reporte . " - " . Carbon::today()->format('d/m/Y'),
                'esPrueba' => true,
                'tipo_reporte' => $configuracion->tipo_reporte,
                'empresa' => $empresa->nombre
            ];

            Mail::to($destinatarios)->send(new ReporteVentasPorVendedor($datos));

            Log::info("Reporte de prueba enviado: {$configuracion->tipo_reporte}", [
                'configuracion_id' => $configuracion->id,
                'destinatarios' => $destinatarios,
                'fecha' => $fechaInicio . ' al ' . $fechaFin
            ]);

            unlink($filePath);

            return true;
        } catch (\Exception $e) {
            Log::error('Error al enviar reporte de prueba: ' . $e->getMessage(), [
                'configuracion_id' => $configuracion->id ?? null,
                'tipo_reporte' => $configuracion->tipo_reporte ?? null
            ]);
            throw $e;
        }
    }

    public function exportarReporteProgramado($configuracion, $fechaInicio, $fechaFin)
    {
        Log::info("Exportando reporte: {$configuracion->tipo_reporte}", [
            'configuracion_id' => $configuracion->id,
            'fecha' => $fechaInicio . ' al ' . $fechaFin,
        ]);

        // if ($configuracion->tipo_reporte === 'ventas-por-vendedor') {
        //     $export = new VentasPorVendedorExport($fechaInicio, $fechaFin, $configuracion->id_empresa);
        // } elseif ($configuracion->tipo_reporte === 'ventas-por-categoria-vendedor') {
        //     $export = new VentasPorCategoriaVendedorExport($fechaInicio, $fechaFin, $configuracion->id_empresa, $configuracion);
        // } elseif ($configuracion->tipo_reporte === 'estado-financiero-consolidado-sucursales') {
        //     $export = new EstadoFinancieroConsolidadoSucursalesExport($fechaInicio, $fechaFin, $configuracion->id_empresa);
        // } else {
        //     return response()->json(['error' => 'Tipo de reporte no implementado'], 422);
        // }

        switch ($configuracion->tipo_reporte) {
            case 'ventas-por-vendedor':
                $export = new VentasPorVendedorExport($fechaInicio, $fechaFin, $configuracion->id_empresa);
                break;
            case 'ventas-por-categoria-vendedor':
                $export = new VentasPorCategoriaVendedorExport($fechaInicio, $fechaFin, $configuracion->id_empresa, $configuracion);
                break;
            case 'estado-financiero-consolidado-sucursales':
                $export = new EstadoFinancieroConsolidadoSucursalesExport($fechaInicio, $fechaFin, $configuracion->id_empresa);
                break;
            case 'detalle-ventas-vendedor':
                $export = new DetalleVentasVendedorExport($fechaInicio, $fechaFin, $configuracion->id_empresa, $configuracion->sucursales);
                break;
            case 'inventario-por-sucursal':
                $export = new InventarioExport($fechaInicio, $fechaFin, $configuracion->id_empresa, $configuracion);
                break;
            case 'ventas-por-utilidades':
                $request = new Request([
                    'id_empresa' => $configuracion->id_empresa,
                    'inicio' => $fechaInicio,
                    'fin' => $fechaFin,
                    'sucursales' => $configuracion->sucursales ?? [],
                ]);
                $export = new VentasPorUtilidadesExport();
                $export->filter($request);
                break;
            default:
                return response()->json(['error' => 'Tipo de reporte no implementado'], 422);
        }

        return \Maatwebsite\Excel\Facades\Excel::download($export, $configuracion->tipo_reporte . '-' . $fechaInicio . '-' . $fechaFin . '.xlsx');
    }

    public function getNumerosIdentificacion()
    {
        $numsIds = Venta::select('num_identificacion')
            ->distinct()
            ->where('id_empresa', auth()->user()->id_empresa)
            ->whereNotNull('num_identificacion')
            ->where('num_identificacion', '!=', '')
            ->get();

        return response()->json($numsIds, 200);
    }
}

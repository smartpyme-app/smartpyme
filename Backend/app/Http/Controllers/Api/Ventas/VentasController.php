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
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

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
            ->with('devoluciones', 'detalles.composiciones', 'detalles.vendedor', 'detalles.producto', 'abonos.usuario', 'cliente', 'impuestos.impuesto', 'metodos_de_pago')
            ->firstOrFail();

        $venta->saldo = $venta->saldo;
        
        return response()->json($venta, 200);
    }

    public function store(StoreVentaRequest $request)
    {
        $venta = Venta::where('id', $request->id)
            ->with('detalles')
            ->firstOrFail();

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
            }

            $venta->fill($request->validated());
            $venta->save();

            DB::commit();
            return response()->json($venta, 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 400);
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
            // Si es cotización, guardar en cotizacion_ventas
            if ($request->cotizacion == 1) {
                $data = $request->validated();
                
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
            $data = $request->validated();
            
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
        return $this->reporteEmailService->enviarReporteProgramado($configuracion, $empresa, $fechaInicio, $fechaFin);
    }

    public function enviarReporteProgramadoTest($configuracion, $destinatarios, $fechaInicio, $fechaFin)
    {
        return $this->reporteEmailService->enviarReportePrueba($configuracion, $destinatarios, $fechaInicio, $fechaFin);
    }

    public function exportarReporteProgramado($configuracion, $fechaInicio, $fechaFin)
    {
        Log::info("Exportando reporte: {$configuracion->tipo_reporte}", [
            'configuracion_id' => $configuracion->id,
            'fecha' => $fechaInicio . ' al ' . $fechaFin,
        ]);

        $export = $this->reporteService->crearExportPorTipo($configuracion, $fechaInicio, $fechaFin, $configuracion->id_empresa);

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

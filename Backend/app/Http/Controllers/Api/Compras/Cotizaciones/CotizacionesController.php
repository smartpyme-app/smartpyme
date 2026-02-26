<?php

namespace App\Http\Controllers\Api\Compras\Cotizaciones;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Registros\Cliente;
use App\Models\Compras\Compra as Cotizacion;
use App\Models\Admin\Empresa;
use App\Models\Compras\Detalle;
// Usamos app('dompdf.wrapper') para evitar errores de Facade en producción
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Carbon\Carbon;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use App\Exports\OrdenesDeComprasExport;
use App\Models\Compras\Compra;
use App\Models\OrdenCompra;
use App\Models\OrdenCompraDetalle;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use App\Http\Requests\Compras\Cotizaciones\StoreOrdenCompraRequest;
use App\Http\Requests\Compras\Cotizaciones\FacturacionCotizacionCompraRequest;
use App\Services\Compras\OrdenCompraService;

class CotizacionesController extends Controller
{
    protected $ordenCompraService;

    public function __construct(OrdenCompraService $ordenCompraService)
    {
        $this->ordenCompraService = $ordenCompraService;
    }

    public function index(Request $request)
    {
        $cotizaciones = $this->ordenCompraService->listarOrdenes($request);
        return Response()->json($cotizaciones, 200);
    }

    public function read($id)
    {

        $cotizacion = OrdenCompra::where('id', $id)->with('proveedor', 'detalles')->firstOrFail();
        return Response()->json($cotizacion, 200);
    }

    public function search($txt)
    {
        $cotizaciones = $this->ordenCompraService->buscarOrdenes($txt);
        return Response()->json($cotizaciones, 200);
    }

    public function filter(Request $request)
    {
        // Normalizar nombres de campos para compatibilidad
        if ($request->sucursal_id) {
            $request->merge(['id_sucursal' => $request->sucursal_id]);
        }
        if ($request->usuario_id) {
            $request->merge(['id_usuario' => $request->usuario_id]);
        }

        // Establecer paginación por defecto alta para mantener compatibilidad
        // con consumidores que esperan recibir todos los resultados filtrados
        if (!$request->has('paginate')) {
            $request->merge(['paginate' => 100000]);
        }

        $cotizaciones = $this->ordenCompraService->listarOrdenes($request);
        return Response()->json($cotizaciones, 200);
    }

    public function store(StoreOrdenCompraRequest $request)
    {
        // VERIFICAR AUTORIZACIÓN por niveles de monto
        $validacion = $this->ordenCompraService->validarAutorizacionRequerida($request);

        if ($validacion['requires_authorization']) {
            return response()->json($validacion, 403);
        }

        Log::info("Procesando orden de compra normal o autorizada");

        DB::beginTransaction();

        try {
            $cotizacion = $this->ordenCompraService->crearOActualizarOrden($request);

            DB::commit();
            return Response()->json($cotizacion, 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                "error" => $e->getMessage()
            ], 400);
        }
    }

    public function facturacion(FacturacionCotizacionCompraRequest $request)
    {

        // Guardamos el proveedor
        if (isset($request->proveedor['id']) || isset($request->proveedor['nombre'])) {
            if (isset($request->proveedor['id']))
                $proveedor = Cliente::findOrFail($request->proveedor['id']);
            else
                $proveedor = new Cliente;

            $proveedor->fill($request->proveedor);
            $proveedor->save();
            $request['proveedor_id'] = $proveedor->id;
        }

        // Guardamos la cotizacion
        if ($request->id)
            $cotizacion = Cotizacion::findOrFail($request->id);
        else
            $cotizacion = new Cotizacion;

        $cotizacion->fill($request->all());
        $cotizacion->save();


        // Guardamos los detalles

        foreach ($request->detalles as $det) {
            if (isset($det['id']))
                $detalle = Detalle::findOrFail($det['id']);
            else
                $detalle = new Detalle;

            $det['cotizacion_id'] = $cotizacion->id;

            $detalle->fill($det);
            $detalle->save();
        }


        return Response()->json($cotizacion, 200);
    }


    public function delete($id)
    {
        try {
            $cotizacion = $this->ordenCompraService->eliminarOrdenCompra($id);
            return Response()->json($cotizacion, 201);
        } catch (\Exception $e) {
            return Response()->json([
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function generarDoc($id)
    {
        $compra = OrdenCompra::where('id', $id)
            ->with(['detalles.producto', 'proveedor', 'empresa.currency'])
            ->firstOrFail();

        // Asegurar que los detalles estén cargados para que los accessors funcionen
        if (!$compra->relationLoaded('detalles')) {
            $compra->load('detalles.producto');
        }

        $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.orden-de-compra', compact('compra'));
        $pdf->setPaper('US Letter', 'portrait');
        return $pdf->stream('orden-de-compra-' . $compra->id . '.pdf');
    }

    public function vendedor()
    {
        $usuarioId = JWTAuth::parseToken()->authenticate()->id;
        $cotizaciones = $this->ordenCompraService->obtenerOrdenesPorVendedor($usuarioId);
        return Response()->json($cotizaciones, 200);
    }

    public function vendedorBuscador($txt)
    {
        $usuarioId = JWTAuth::parseToken()->authenticate()->id;
        $cotizaciones = $this->ordenCompraService->buscarOrdenesPorVendedor($usuarioId, $txt);
        return Response()->json($cotizaciones, 200);
    }

    public function export(Request $request)
    {
        $cotizaciones = new OrdenesDeComprasExport();
        $cotizaciones->filter($request);

        return Excel::download($cotizaciones, 'cotizaciones.xlsx');
    }

    public function procesarOrdenAutorizada($ordenId)
    {
        try {
            $orden = $this->ordenCompraService->procesarOrdenAutorizada($ordenId);
            return $orden;
        } catch (\Exception $e) {
            Log::error("Error procesando orden de compra autorizada: " . $e->getMessage());
            throw $e;
        }
    }

    protected function handlePendingAuthorization($data, $authorization)
    {
        $resultado = $this->ordenCompraService->handlePendingAuthorization($data, $authorization);

        if ($resultado['ok']) {
            return response()->json($resultado);
        } else {
            return response()->json($resultado, 403);
        }
    }

    public function solicitudes(Request $request)
    {
        try {
            $user = Auth::user();
            $cotizaciones = $this->ordenCompraService->obtenerSolicitudes($request, $user);
            return Response()->json($cotizaciones, 200);
        } catch (\Exception $e) {
            return Response()->json([
                'error' => [$e->getMessage()],
                'code' => 403
            ], 403);
        }
    }

    public function solicitud($id) {

        $cotizacion = Cotizacion::withoutGlobalScope('empresa')->where('id', $id)->with('proveedor', 'detalles')->firstOrFail();
        return Response()->json($cotizacion, 200);

    }
}

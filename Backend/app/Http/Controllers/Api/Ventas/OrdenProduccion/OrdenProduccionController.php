<?php

namespace App\Http\Controllers\Api\Ventas\OrdenProduccion;

use App\Constants\CotizacionConstants;
use App\Http\Controllers\Controller;
use App\Models\Admin\Notificacion;
use App\Models\CotizacionVenta;
use App\Models\CotizacionVentaDetalle;
use App\Models\Inventario\CustomFields\ProductCustomField;
use App\Models\Ventas\Orden_Produccion\OrdenProduccion;
use App\Models\Ventas\Orden_Produccion\DetalleOrdenProduccion;
use App\Models\Ventas\OrdenProduccion\HistorialOrdenProduccion;
use App\Models\Ventas\OrdenProduccion\NotificacionOrdenProduccion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\Ventas\OrdenProduccion\StoreOrdenProduccionRequest;
use App\Http\Requests\Ventas\OrdenProduccion\UpdateOrdenProduccionRequest;
use App\Http\Requests\Ventas\OrdenProduccion\CambiarEstadoOrdenRequest;
use App\Services\Ventas\OrdenProduccionService;

class OrdenProduccionController extends Controller
{
    protected $ordenProduccionService;

    public function __construct(OrdenProduccionService $ordenProduccionService)
    {
        $this->ordenProduccionService = $ordenProduccionService;
    }
    public function index(Request $request)
    {
        $query = OrdenProduccion::withAccessorRelations()
            ->with(['asesor']) // Mantener asesor que se usa en filtros
            ->when($request->estado, function ($q, $estado) {
                return $q->where('estado', $estado);
            })
            ->when($request->fecha_entrega, function ($q, $fecha) {
                return $q->whereDate('fecha_entrega', $fecha);
            })
            ->when($request->id_asesor, function ($q, $asesor) {
                return $q->where('id_asesor', $asesor);
            })
            ->when($request->buscador, function ($q, $busqueda) {
                return $q->where(function ($query) use ($busqueda) {
                    $query->where('codigo', 'like', '%' . $busqueda . '%')
                    ->orWhereHas('cliente', function ($clienteQuery) use ($busqueda) {
                        $clienteQuery->where('nombre', 'like', '%' . $busqueda . '%')
                            ->orWhere('apellido', 'like', '%' . $busqueda . '%')
                            ->orWhere('nombre_empresa', 'like', '%' . $busqueda . '%')
                            ->orWhere(DB::raw("CONCAT(nombre, ' ', apellido)"), 'like', '%' . $busqueda . '%');
                    })
                    ->orWhereHas('asesor', function ($asesorQuery) use ($busqueda) {
                        $asesorQuery->where('name', 'like', '%' . $busqueda . '%');
                    });
                });
            })
            ->orderBy('id', 'desc');
    
        return response()->json([
            'success' => true,
            'data' => $query->paginate($request->perPage ?? 10)
        ]);
    }

    public function store(StoreOrdenProduccionRequest $request)
    {
        try {
            DB::beginTransaction();
    
            $ordenData = json_decode($request->datos_orden, true);
    
            // Si es actualización
            if (isset($ordenData['id'])) {
                $orden = OrdenProduccion::findOrFail($ordenData['id']);
                $resultado = $this->ordenProduccionService->actualizarOrdenProduccion($orden, $ordenData);
    
                DB::commit();
                return response()->json([
                    'success' => true,
                    'message' => 'Orden de producción actualizada exitosamente',
                    'action' => 'updated'
                ]);
            }
    
            // Si es creación
            $documentoPdf = $request->hasFile('documento_pdf') ? $request->file('documento_pdf') : null;
            $orden = $this->ordenProduccionService->crearOrdenProduccion($ordenData, $documentoPdf);
    
            DB::commit();
    
            return response()->json([
                'success' => true,
                'message' => 'Orden de producción creada exitosamente',
                'action' => 'created',
                'data' => $orden->fresh(['detalles', 'cliente', 'usuario', 'asesor'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
    
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la orden',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getDocumento(Request $request)
    {
        try {
            $documento = DB::table('orden_produccion_documentos')
                ->where('id_orden_produccion', $request->id)
                ->first();

            if (!$documento) {
                return response()->json([
                    'success' => false,
                    'message' => 'Documento no encontrado'
                ], 404);
            }
            $path = public_path('img/' . $documento->ruta_archivo);
            //$path = storage_path('app/public/' . $documento->ruta_archivo);

            Log::info($path);

            if (!file_exists($path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Archivo no encontrado'
                ], 404);
            }

            return response()->file($path, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $documento->nombre_archivo . '"'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el documento',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function read($id)
    {
        $orden = OrdenProduccion::with([
            'detalles.producto',
            'documentoOrden',
            'detalles.customFields.customFieldValue',
            'cliente',
            'usuario',
            'asesor',
            'historial.usuario',
            'vendedor'
        ])->findOrFail($id);

        // Log::info($orden);

        return response()->json($orden);
    }


    public function update(UpdateOrdenProduccionRequest $request, $id)
    {
        try {
            DB::beginTransaction();

            $orden = OrdenProduccion::findOrFail($id);
            $orden = $this->ordenProduccionService->actualizarOrden($orden, $request->all());

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Orden actualizada exitosamente',
                'data' => $orden->fresh(['detalles', 'cliente', 'usuario', 'asesor'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la orden',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function cambiarEstado(CambiarEstadoOrdenRequest $request)
    {
        try {
            DB::beginTransaction();

            $orden = OrdenProduccion::findOrFail($request->id);
            $resultado = $this->ordenProduccionService->cambiarEstado($orden, $request->estado, false);

            DB::commit();

            return response()->json([
                'success' => $resultado['success'],
                'message' => $resultado['message'],
                'data' => $resultado['orden']->fresh()
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al cambiar el estado',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function anular(Request $request)
    {
        try {
            DB::beginTransaction();

            $orden = OrdenProduccion::findOrFail($request->id);
            $orden = $this->ordenProduccionService->anularOrden($orden);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Orden anulada exitosamente',
                'data' => $orden
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al anular la orden',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //imprimir
    public function imprimir($id)
    {
        $orden = OrdenProduccion::with(['cliente', 'empresa', 'detalles.producto', 'detalles.customFields.customFieldValue', 'cliente', 'usuario', 'asesor'])->findOrFail($id);
        // return response()->json($orden->detalles);
        $pdf = PDF::loadView('reportes.facturacion.orden_produccion', compact('orden'));
        $pdf->setPaper('US Letter', 'portrait');
        return $pdf->stream('orden_produccion-' . $orden->id . '.pdf');
    }

    public function changeStateOrden(CambiarEstadoOrdenRequest $request)
    {
        try {
            DB::beginTransaction();

            $orden = OrdenProduccion::findOrFail($request->id);
            $resultado = $this->ordenProduccionService->cambiarEstado($orden, $request->estado, true);

            DB::commit();

            if (!$resultado['success']) {
                return response()->json($resultado, 400);
            }

            return response()->json([
                'success' => true,
                'message' => $resultado['message'],
                'data' => $resultado['orden']
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al cambiar el estado',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

<?php

namespace App\Http\Controllers\Api\Ventas\OrdenProduccion;

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
use Barryvdh\DomPDF\Facade as PDF;
use Illuminate\Support\Facades\Storage;

class OrdenProduccionController extends Controller
{
    public function index(Request $request)
    {

        $query = OrdenProduccion::with(['cliente', 'usuario', 'asesor'])
            //  ->where('id_empresa', Auth::user()->id_empresa)
            ->when($request->estado, function ($q, $estado) {
                return $q->where('estado', $estado);
            })
            ->when($request->fecha_entrega, function ($q, $fecha) {
                return $q->whereDate('fecha_entrega', $fecha);
            })
            ->when($request->id_asesor, function ($q, $asesor) {
                return $q->where('id_asesor', $asesor);
            })
            ->orderBy('id', 'desc');

        return response()->json([
            'success' => true,
            'data' => $query->paginate($request->perPage ?? 10)
        ]);
    }

    // public function store(Request $request)
    // {
    //     // dd($request->all());
    //     try {
    //         DB::beginTransaction();

    //         // Validar request
    //         $request->validate([
    //             'fecha' => 'required|date',
    //             'fecha_entrega' => 'required|date',
    //             'id_cliente' => 'required|exists:clientes,id',
    //             //  'id_asesor' => 'required|exists:users,id',
    //         ]);

    //         if ($request->id) {
    //             $orden = OrdenProduccion::findOrFail($request->id);
    //             $orden->update([
    //                 'estado' => $request->estado,
    //             ]);

    //             if ($request->has('detalles')) {

    //                 $detallesIds = $orden->detalles()->pluck('id')->toArray();

    //                 foreach ($request->detalles as $detalle) {
    //                     if (isset($detalle['id']) && in_array($detalle['id'], $detallesIds)) {

    //                         $orden->detalles()
    //                             ->where('id', $detalle['id'])
    //                             ->update([
    //                                 'cantidad_producida' => $detalle['cantidad_producida']
    //                             ]);
    //                     }
    //                 }

    //                 DB::commit();
    //                 return response()->json([
    //                     'success' => true,
    //                     'message' => 'Orden actualizada exitosamente'
    //                 ]);
    //             }
    //         }
    //         $cotizacion = CotizacionVenta::find($request->id_cotizacion);
    //         $id_empresa = Auth::user()->id_empresa;

    //         $orden = OrdenProduccion::create([
    //             'codigo' => $this->generarCodigo(),
    //             'fecha' => $request->fecha,
    //             'fecha_entrega' => $request->fecha_entrega,
    //             'estado' => 'pendiente',
    //             'id_cotizacion_venta' => $cotizacion->id,
    //             'id_cliente' => $cotizacion->id_cliente,
    //             'id_usuario' => Auth::id(),
    //             'id_asesor' => $request->id_asesor,
    //             'observaciones' => $request->observaciones,
    //             'id_empresa' => $id_empresa,
    //             'id_bodega' => $cotizacion->id_bodega,
    //             'terminos_condiciones' => $cotizacion->terminos_de_venta,
    //             'id_vendedor' => $cotizacion->id_vendedor
    //         ]);

    //         $cotizacion = CotizacionVenta::with('detalles.customFields.customFieldValue')->find($cotizacion->id);

    //         foreach ($cotizacion->detalles as $detalle) {

    //             $orden_produccion = DetalleOrdenProduccion::create([
    //                 'id_orden_produccion' => $orden->id,
    //                 'id_producto' => $detalle->id_producto,
    //                 'cantidad' => $detalle->cantidad,
    //                 'precio' => $detalle->precio,
    //                 'total' => $detalle->total,
    //                 'total_costo' => $detalle->total_costo,
    //                 'descuento' => $detalle->descuento,
    //                 'subtotal' => $detalle->subtotal
    //             ]);
    //             foreach ($detalle->customFields as $customField) {
    //                 ProductCustomField::create([
    //                     'custom_field_id' => $customField->custom_field_id,
    //                     'custom_field_value_id' => $customField->custom_field_value_id,
    //                     'orden_produccion_detalle_id' => $orden_produccion->id,
    //                     'value' => $customField->value
    //                 ]);
    //             }
    //         }

    //         $this->calcularTotales($orden);

    //         // Registrar historial
    //         $orden->historial()->create([
    //             'estado_nuevo' => 'pendiente',
    //             'id_usuario' => Auth::id(),
    //             'comentarios' => 'Orden creada'
    //         ]);


    //         DB::commit();

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Orden creada exitosamente',
    //             'data' => $orden->fresh(['detalles', 'cliente', 'usuario', 'asesor'])
    //         ]);
    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Error al crear la orden',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    public function store(Request $request)
    {
        try {
            DB::beginTransaction();

            // Decodificar los datos de la orden
            $ordenData = json_decode($request->datos_orden, true);

            // Validar request
            $request->validate([
                'documento_pdf' => 'nullable|file|mimes:pdf|max:5120', // 5MB max
                'datos_orden' => 'required|json'
            ]);

            if (isset($ordenData['id'])) {
                $orden = OrdenProduccion::findOrFail($ordenData['id']);
                $orden->update([
                    'estado' => $ordenData['estado'],
                ]);

                if (isset($ordenData['detalles'])) {
                    $detallesIds = $orden->detalles()->pluck('id')->toArray();

                    foreach ($ordenData['detalles'] as $detalle) {
                        if (isset($detalle['id']) && in_array($detalle['id'], $detallesIds)) {
                            $orden->detalles()
                                ->where('id', $detalle['id'])
                                ->update([
                                    'cantidad_producida' => $detalle['cantidad_producida']
                                ]);
                        }
                    }

                    DB::commit();
                    return response()->json([
                        'success' => true,
                        'message' => 'Orden actualizada exitosamente'
                    ]);
                }
            }

            $cotizacion = CotizacionVenta::find($ordenData['id_cotizacion']);
            $id_empresa = Auth::user()->id_empresa;

            $orden = OrdenProduccion::create([
                'codigo' => $this->generarCodigo(),
                'fecha' => $ordenData['fecha'],
                'fecha_entrega' => $ordenData['fecha_entrega'],
                'estado' => 'pendiente',
                'id_cotizacion_venta' => $cotizacion->id,
                'id_cliente' => $cotizacion->id_cliente,
                'id_usuario' => Auth::id(),
                'id_asesor' => $ordenData['id_asesor'],
                'observaciones' => $ordenData['observaciones'],
                'id_empresa' => $id_empresa,
                'id_bodega' => $cotizacion->id_bodega,
                'terminos_condiciones' => $ordenData['terminos_de_venta'],
                'id_vendedor' => $cotizacion->id_vendedor
            ]);


            if ($request->hasFile('documento_pdf')) {
                $file = $request->file('documento_pdf');
                $fileName = time() . '_' . $file->getClientOriginalName();
                //almacenar en storage Storage::disk('public')->put('ordenes_produccion/' . $fileName, file_get_contents($file));
                $path = 'ordenes_produccion/' . $fileName;
                Storage::disk('public')->put($path, file_get_contents($file));



                DB::table('orden_produccion_documentos')->insert([
                    'id_orden_produccion' => $orden->id,
                    'nombre_archivo' => $file->getClientOriginalName(),
                    'ruta_archivo' => $path,
                    'mime_type' => $file->getMimeType(),
                    'tamano' => $file->getSize(),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }


            $cotizacion = CotizacionVenta::with('detalles.customFields.customFieldValue')->find($cotizacion->id);

            foreach ($cotizacion->detalles as $detalle) {
                $orden_produccion = DetalleOrdenProduccion::create([
                    'id_orden_produccion' => $orden->id,
                    'id_producto' => $detalle->id_producto,
                    'cantidad' => $detalle->cantidad,
                    'precio' => $detalle->precio,
                    'total' => $detalle->total,
                    'total_costo' => $detalle->total_costo,
                    'descuento' => $detalle->descuento,
                    'subtotal' => $detalle->subtotal
                ]);

                foreach ($detalle->customFields as $customField) {
                    ProductCustomField::create([
                        'custom_field_id' => $customField->custom_field_id,
                        'custom_field_value_id' => $customField->custom_field_value_id,
                        'orden_produccion_detalle_id' => $orden_produccion->id,
                        'value' => $customField->value
                    ]);
                }
            }

            $this->calcularTotales($orden);


            $orden->historial()->create([
                'estado_nuevo' => 'pendiente',
                'id_usuario' => Auth::id(),
                'comentarios' => 'Orden creada'
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Orden creada exitosamente',
                'data' => $orden->fresh(['detalles', 'cliente', 'usuario', 'asesor'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            if (isset($path)) {
                Storage::disk('public')->delete($path);
            }

            return response()->json([
                'success' => false,
                'message' => 'Error al crear la orden',
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


    public function update(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $orden = OrdenProduccion::findOrFail($id);

            // Validar que la orden no esté anulada
            if ($orden->estado === 'anulada') {
                throw new \Exception('No se puede modificar una orden anulada');
            }

            $request->validate([
                'fecha_entrega' => 'sometimes|date',
                'observaciones' => 'sometimes|string',
                'detalles' => 'sometimes|array'
            ]);

            // Actualizar campos básicos
            $orden->update($request->only([
                'fecha_entrega',
                'observaciones'
            ]));

            // Actualizar detalles si se proporcionaron
            if ($request->has('detalles')) {
                // Eliminar detalles existentes
                $orden->detalles()->delete();

                // Crear nuevos detalles
                foreach ($request->detalles as $detalle) {
                    $orden->detalles()->create([
                        'id_producto' => $detalle['id_producto'],
                        'cantidad' => $detalle['cantidad'],
                        'precio' => $detalle['precio'],
                        'total' => $detalle['cantidad'] * $detalle['precio'],
                        'descripcion' => $detalle['descripcion'] ?? null
                    ]);
                }

                // Recalcular totales
                $this->calcularTotales($orden);
            }

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

    public function cambiarEstado(Request $request)
    {
        try {
            DB::beginTransaction();

            $request->validate([
                'estado' => 'required|in:pendiente,aceptada,en_proceso,completada,entregada,anulada',
                'comentarios' => 'nullable|string'
            ]);

            $orden = OrdenProduccion::findOrFail($request->id);
            $estadoAnterior = $orden->estado;

            $orden->update(['estado' => $request->estado]);


            $orden->historial()->create([
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => $request->estado,
                'id_usuario' => Auth::id(),
                'comentarios' => 'Estado actualizado a ' . $request->estado
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Estado actualizado exitosamente',
                'data' => $orden->fresh()
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

    private function calcularTotales(OrdenProduccion $orden)
    {
        $detalles = $orden->detalles;

        $subtotal = $detalles->sum('total');
        $totalCosto = $detalles->sum('total_costo');
        $descuento = $detalles->sum('descuento');

        // Actualizar totales en la orden
        $orden->update([
            'subtotal' => $subtotal,
            'total_costo' => $totalCosto,
            'descuento' => $descuento,
            'total' => $subtotal - $descuento
        ]);
    }

    private function generarCodigo()
    {
        $ultimaOrden = OrdenProduccion::latest('id')->first();
        $numeroActual = $ultimaOrden ? intval(substr($ultimaOrden->codigo, 3)) + 1 : 1;
        return 'OP-' . str_pad($numeroActual, 6, '0', STR_PAD_LEFT);
    }
    //anular
    public function anular(Request $request)
    {
        $orden = OrdenProduccion::findOrFail($request->id);
        $orden->update(['estado' => 'anulada']);
        return response()->json(['success' => true, 'message' => 'Orden anulada exitosamente']);
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
}

<?php

namespace App\Http\Controllers\Api\Ventas\OrdenProduccion;

use App\Http\Controllers\Controller;
use App\Models\Ventas\Orden_Produccion\OrdenProduccion;
use App\Models\Ventas\OrdenProduccion\DetalleOrdenProduccion;
use App\Models\Ventas\OrdenProduccion\HistorialOrdenProduccion;
use App\Models\Ventas\OrdenProduccion\NotificacionOrdenProduccion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class OrdenProduccionController extends Controller
{
    public function index(Request $request)
    {
        $query = OrdenProduccion::with(['cliente', 'usuario', 'asesor'])
            ->when($request->estado, function($q, $estado) {
                return $q->where('estado', $estado);
            })
            ->when($request->fecha_entrega, function($q, $fecha) {
                return $q->whereDate('fecha_entrega', $fecha);
            })
            ->when($request->id_asesor, function($q, $asesor) {
                return $q->where('id_asesor', $asesor);
            });

        return response()->json([
            'success' => true,
            'data' => $query->paginate($request->perPage ?? 10)
        ]);
    }

    public function store(Request $request)
    {
        try {
            DB::beginTransaction();

            // Validar request
            $request->validate([
                'fecha' => 'required|date',
                'fecha_entrega' => 'required|date',
                'id_cliente' => 'required|exists:clientes,id',
                'id_asesor' => 'required|exists:users,id',
                'detalles' => 'required|array|min:1',
                'detalles.*.id_producto' => 'required|exists:productos,id',
                'detalles.*.cantidad' => 'required|integer|min:1',
                'detalles.*.precio' => 'required|numeric|min:0'
            ]);

            // Crear la orden
            $orden = OrdenProduccion::create([
                'codigo' => $this->generarCodigo(),
                'fecha' => $request->fecha,
                'fecha_entrega' => $request->fecha_entrega,
                'estado' => 'pendiente',
                'id_cliente' => $request->id_cliente,
                'id_usuario' => Auth::id(),
                'id_asesor' => $request->id_asesor,
                'observaciones' => $request->observaciones
            ]);

            // Crear detalles
            foreach ($request->detalles as $detalle) {
                $orden->detalles()->create([
                    'id_producto' => $detalle['id_producto'],
                    'cantidad' => $detalle['cantidad'],
                    'precio' => $detalle['precio'],
                    'total' => $detalle['cantidad'] * $detalle['precio'],
                    'descripcion' => $detalle['descripcion'] ?? null
                ]);
            }

            // Registrar historial
            $orden->historial()->create([
                'estado_nuevo' => 'pendiente',
                'id_usuario' => Auth::id(),
                'comentarios' => 'Orden creada'
            ]);

            // Crear notificación
            // NotificacionOrdenProduccion::create([
            //     'id_orden_produccion' => $orden->id,
            //     'tipo' => 'nueva_orden',
            //     'mensaje' => "Nueva orden de producción #{$orden->codigo} creada"
            // ]);

            // Calcular totales
            $this->calcularTotales($orden);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Orden creada exitosamente',
                'data' => $orden->fresh(['detalles', 'cliente', 'usuario', 'asesor'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la orden',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function read($id)
    {
        $orden = OrdenProduccion::with([
            'detalles.producto',
            'detalles.customFields',
            'cliente',
            'usuario',
            'asesor',
            'historial.usuario'
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $orden
        ]);
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

    public function cambiarEstado(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $request->validate([
                'estado' => 'required|in:pendiente,aceptada,en_proceso,completada,entregada,anulada',
                'comentarios' => 'nullable|string'
            ]);

            $orden = OrdenProduccion::findOrFail($id);
            $estadoAnterior = $orden->estado;

            // Validar cambio de estado
            if ($estadoAnterior === 'anulada') {
                throw new \Exception('No se puede cambiar el estado de una orden anulada');
            }

            // Actualizar estado
            $orden->update(['estado' => $request->estado]);

            // Registrar historial
            $orden->historial()->create([
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => $request->estado,
                'id_usuario' => Auth::id(),
                'comentarios' => $request->comentarios
            ]);

            // Crear notificación si el estado es completada
            // if ($request->estado === 'completada') {
            //     NotificacionOrdenProduccion::create([
            //         'id_orden_produccion' => $orden->id,
            //         'tipo' => 'orden_lista',
            //         'mensaje' => "La orden #{$orden->codigo} ha sido completada"
            //     ]);
            // }

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
}
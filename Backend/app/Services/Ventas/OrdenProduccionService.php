<?php

namespace App\Services\Ventas;

use App\Constants\CotizacionConstants;
use App\Models\CotizacionVenta;
use App\Models\Inventario\CustomFields\ProductCustomField;
use App\Models\Ventas\Orden_Produccion\OrdenProduccion;
use App\Models\Ventas\Orden_Produccion\DetalleOrdenProduccion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class OrdenProduccionService
{
    /**
     * Crea una nueva orden de producción desde una cotización
     *
     * @param array $ordenData
     * @param \Illuminate\Http\UploadedFile|null $documentoPdf
     * @return OrdenProduccion
     */
    public function crearOrdenProduccion(array $ordenData, $documentoPdf = null): OrdenProduccion
    {
        $cotizacion = CotizacionVenta::findOrFail($ordenData['id_cotizacion']);
        $id_empresa = Auth::user()->id_empresa;

        $orden = OrdenProduccion::create([
            'codigo' => $this->generarCodigo(),
            'fecha' => $ordenData['fecha'],
            'fecha_entrega' => $ordenData['fecha_entrega'],
            'estado' => CotizacionConstants::ESTADO_PRODUCCION_CREADA,
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

        // Guardar documento PDF si existe
        if ($documentoPdf) {
            $this->guardarDocumento($orden, $documentoPdf);
        }

        // Crear detalles desde la cotización
        $this->crearDetallesDesdeCotizacion($orden, $cotizacion);

        // Calcular totales
        $this->calcularTotales($orden);

        // Registrar historial inicial
        $this->registrarHistorial($orden, null, CotizacionConstants::ESTADO_PRODUCCION_CREADA, 'Orden creada');

        return $orden;
    }

    /**
     * Actualiza una orden de producción existente
     *
     * @param OrdenProduccion $orden
     * @param array $ordenData
     * @return array ['orden' => OrdenProduccion, 'huboCambios' => bool]
     */
    public function actualizarOrdenProduccion(OrdenProduccion $orden, array $ordenData): array
    {
        $hubocambiosCantidad = false;

        // Actualizar estado
        $orden->update([
            'estado' => $ordenData['estado'],
        ]);

        // Actualizar detalles si existen
        if (isset($ordenData['detalles'])) {
            $detallesIds = $orden->detalles()->pluck('id')->toArray();

            foreach ($ordenData['detalles'] as $detalle) {
                if (isset($detalle['id']) && in_array($detalle['id'], $detallesIds)) {
                    // Verificar si hay cambio en cantidad_producida
                    if (isset($detalle['cantidad_producida'])) {
                        $detalleActual = $orden->detalles()->where('id', $detalle['id'])->first();

                        if ($detalleActual && $detalleActual->cantidad_producida != $detalle['cantidad_producida']) {
                            $hubocambiosCantidad = true;
                        }
                    }

                    $orden->detalles()
                        ->where('id', $detalle['id'])
                        ->update([
                            'cantidad_producida' => $detalle['cantidad_producida']
                        ]);
                }
            }

            // Manejar cambios de estado automáticos
            if ($hubocambiosCantidad) {
                $this->manejarCambiosEstadoAutomaticos($orden);
            }
        }

        return [
            'orden' => $orden,
            'huboCambios' => $hubocambiosCantidad
        ];
    }

    /**
     * Maneja los cambios de estado automáticos basados en la producción
     *
     * @param OrdenProduccion $orden
     * @return void
     */
    public function manejarCambiosEstadoAutomaticos(OrdenProduccion $orden): void
    {
        // Si el estado es 'aceptada' y hay cambios, cambiar a 'en_proceso'
        if ($orden->estado === 'aceptada') {
            $estadoAnterior = $orden->estado;
            $orden->update(['estado' => 'en_proceso']);

            $this->registrarHistorial(
                $orden,
                $estadoAnterior,
                'en_proceso',
                'Estado cambiado automáticamente por actualización de cantidad producida'
            );
        }

        // Verificar si la producción está completada cuando el estado es 'en_proceso'
        if ($orden->estado === 'en_proceso') {
            $orden = $orden->fresh(['detalles']);

            if ($this->isProduccionCompletada($orden)) {
                $estadoAnterior = $orden->estado;
                $orden->update(['estado' => 'completada']);

                $this->registrarHistorial(
                    $orden,
                    $estadoAnterior,
                    'completada',
                    'Producción completada automáticamente - todas las cantidades fueron producidas'
                );
            }
        }
    }

    /**
     * Verifica si la producción está completada
     *
     * @param OrdenProduccion $orden
     * @return bool
     */
    public function isProduccionCompletada(OrdenProduccion $orden): bool
    {
        foreach ($orden->detalles as $detalle) {
            if ($detalle->cantidad_producida < $detalle->cantidad) {
                return false;
            }
        }
        return true;
    }

    /**
     * Obtiene los detalles incompletos de una orden
     *
     * @param OrdenProduccion $orden
     * @return array
     */
    public function obtenerDetallesIncompletos(OrdenProduccion $orden): array
    {
        $detallesIncompletos = [];
        
        foreach ($orden->detalles as $detalle) {
            if ($detalle->cantidad_producida < $detalle->cantidad) {
                $detallesIncompletos[] = [
                    'producto_id' => $detalle->id_producto,
                    'cantidad_requerida' => $detalle->cantidad,
                    'cantidad_producida' => $detalle->cantidad_producida,
                    'cantidad_faltante' => $detalle->cantidad - $detalle->cantidad_producida
                ];
            }
        }

        return $detallesIncompletos;
    }

    /**
     * Cambia el estado de una orden con validaciones
     *
     * @param OrdenProduccion $orden
     * @param string $nuevoEstado
     * @param bool $validarCompletitud Si true, valida que la producción esté completa para estado 'completada'
     * @return array ['success' => bool, 'message' => string, 'detalles_incompletos' => array|null]
     */
    public function cambiarEstado(OrdenProduccion $orden, string $nuevoEstado, bool $validarCompletitud = true): array
    {
        // Validar si se intenta cambiar a estado "completada"
        if ($validarCompletitud && $nuevoEstado === 'completada') {
            $orden->load('detalles');
            $detallesIncompletos = $this->obtenerDetallesIncompletos($orden);

            if (!empty($detallesIncompletos)) {
                return [
                    'success' => false,
                    'message' => 'No se puede completar la orden. Debe completarse la producción de todos los productos.',
                    'detalles_incompletos' => $detallesIncompletos
                ];
            }
        }

        $estadoAnterior = $orden->estado;
        $orden->estado = $nuevoEstado;
        $orden->save();

        // Registrar el cambio en el historial
        $comentario = $validarCompletitud ? 'Estado cambiado manualmente' : 'Estado actualizado a ' . $nuevoEstado;
        $this->registrarHistorial($orden, $estadoAnterior, $nuevoEstado, $comentario);

        return [
            'success' => true,
            'message' => 'Estado actualizado exitosamente',
            'orden' => $orden
        ];
    }

    /**
     * Anula una orden de producción
     *
     * @param OrdenProduccion $orden
     * @return OrdenProduccion
     */
    public function anularOrden(OrdenProduccion $orden): OrdenProduccion
    {
        $estadoAnterior = $orden->estado;
        $orden->update(['estado' => 'anulada']);

        $this->registrarHistorial($orden, $estadoAnterior, 'anulada', 'Orden anulada');

        return $orden;
    }

    /**
     * Actualiza una orden (método update del controlador)
     *
     * @param OrdenProduccion $orden
     * @param array $data
     * @return OrdenProduccion
     * @throws \Exception
     */
    public function actualizarOrden(OrdenProduccion $orden, array $data): OrdenProduccion
    {
        // Validar que la orden no esté anulada
        if ($orden->estado === 'anulada') {
            throw new \Exception('No se puede modificar una orden anulada');
        }

        // Actualizar campos básicos
        $orden->update([
            'fecha_entrega' => $data['fecha_entrega'] ?? $orden->fecha_entrega,
            'observaciones' => $data['observaciones'] ?? $orden->observaciones
        ]);

        // Actualizar detalles si se proporcionaron
        if (isset($data['detalles'])) {
            // Eliminar detalles existentes
            $orden->detalles()->delete();

            // Crear nuevos detalles
            foreach ($data['detalles'] as $detalle) {
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

        return $orden;
    }

    /**
     * Crea detalles de orden de producción desde una cotización
     *
     * @param OrdenProduccion $orden
     * @param CotizacionVenta $cotizacion
     * @return void
     */
    protected function crearDetallesDesdeCotizacion(OrdenProduccion $orden, CotizacionVenta $cotizacion): void
    {
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

            // Copiar custom fields
            foreach ($detalle->customFields as $customField) {
                ProductCustomField::create([
                    'custom_field_id' => $customField->custom_field_id,
                    'custom_field_value_id' => $customField->custom_field_value_id,
                    'orden_produccion_detalle_id' => $orden_produccion->id,
                    'value' => $customField->value
                ]);
            }
        }
    }

    /**
     * Guarda un documento PDF asociado a la orden
     *
     * @param OrdenProduccion $orden
     * @param \Illuminate\Http\UploadedFile $file
     * @return string Ruta del archivo guardado
     */
    protected function guardarDocumento(OrdenProduccion $orden, $file): string
    {
        $fileName = time() . '_' . $file->getClientOriginalName();
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

        return $path;
    }

    /**
     * Calcula los totales de una orden
     *
     * @param OrdenProduccion $orden
     * @return void
     */
    public function calcularTotales(OrdenProduccion $orden): void
    {
        $detalles = $orden->detalles;

        $subtotal = $detalles->sum('total');
        $totalCosto = $detalles->sum('total_costo');
        $descuento = $detalles->sum('descuento');

        $orden->update([
            'subtotal' => $subtotal,
            'total_costo' => $totalCosto,
            'descuento' => $descuento,
            'total' => $subtotal - $descuento
        ]);
    }

    /**
     * Genera un código único para la orden de producción
     *
     * @return string
     */
    public function generarCodigo(): string
    {
        $ultimaOrden = OrdenProduccion::latest('id')->first();
        $numeroActual = $ultimaOrden ? intval(substr($ultimaOrden->codigo, 3)) + 1 : 1;
        return 'OP-' . str_pad($numeroActual, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Registra un evento en el historial de la orden
     *
     * @param OrdenProduccion $orden
     * @param string|null $estadoAnterior
     * @param string $estadoNuevo
     * @param string $comentarios
     * @return void
     */
    protected function registrarHistorial(OrdenProduccion $orden, ?string $estadoAnterior, string $estadoNuevo, string $comentarios): void
    {
        $orden->historial()->create([
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => $estadoNuevo,
            'id_usuario' => Auth::id(),
            'comentarios' => $comentarios
        ]);
    }
}

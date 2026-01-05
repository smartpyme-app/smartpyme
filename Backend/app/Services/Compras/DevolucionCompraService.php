<?php

namespace App\Services\Compras;

use App\Models\Compras\Devoluciones\Devolucion;
use App\Models\Compras\Devoluciones\Detalle;
use App\Models\Inventario\Inventario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DevolucionCompraService
{
    /**
     * Lista devoluciones con filtros
     *
     * @param array $filters
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function listarDevoluciones(array $filters)
    {
        $query = Devolucion::query();

        if (isset($filters['buscador']) && $filters['buscador']) {
            $query->where('observaciones', 'like', '%' . $filters['buscador'] . '%');
        }

        if (isset($filters['inicio']) && $filters['inicio']) {
            $query->where('fecha', '>=', $filters['inicio']);
        }

        if (isset($filters['fin']) && $filters['fin']) {
            $query->where('fecha', '<=', $filters['fin']);
        }

        if (isset($filters['estado']) && $filters['estado'] !== null) {
            $query->where('enable', (bool)$filters['estado']);
        }

        if (isset($filters['id_usuario']) && $filters['id_usuario']) {
            $query->where('id_usuario', $filters['id_usuario']);
        }

        if (isset($filters['id_proveedor']) && $filters['id_proveedor']) {
            $query->where('id_proveedor', $filters['id_proveedor']);
        }

        if (isset($filters['referencia']) && $filters['referencia']) {
            $buscador = $filters['referencia'];
            $query->where(function($q) use ($buscador) {
                $q->whereHas('proveedor', function($q2) use ($buscador) {
                    $q2->where('nombre', 'like', "%{$buscador}%")
                       ->orWhere('nombre_empresa', 'like', "%{$buscador}%")
                       ->orWhere('ncr', 'like', "%{$buscador}%")
                       ->orWhere('nit', 'like', "%{$buscador}%");
                })
                ->orWhere('referencia', 'like', "%{$buscador}%")
                ->orWhere('observaciones', 'like', "%{$buscador}%")
                ->orWhere('tipo_documento', 'like', "%{$buscador}%")
                ->orWhere(function($q3) use ($buscador) {
                    $q3->where('tipo_documento', 'like', "%{$buscador}%")
                       ->orWhere('referencia', 'like', "%{$buscador}%")
                       ->orWhereRaw("CONCAT(tipo_documento, ' #', referencia) LIKE ?", ["%{$buscador}%"]);
                });
            });
        }

        $orden = $filters['orden'] ?? 'id';
        $direccion = $filters['direccion'] ?? 'desc';
        $paginate = $filters['paginate'] ?? 15;

        return $query->orderBy($orden, $direccion)
            ->orderBy('id', 'desc')
            ->paginate($paginate);
    }

    /**
     * Obtiene una devolución por ID
     *
     * @param int $id
     * @return Devolucion
     */
    public function obtenerDevolucion(int $id): Devolucion
    {
        return Devolucion::where('id', $id)
            ->with('detalles', 'compra', 'proveedor')
            ->firstOrFail();
    }

    /**
     * Crea o actualiza una devolución
     *
     * @param array $data
     * @return Devolucion
     */
    public function crearOActualizarDevolucion(array $data): Devolucion
    {
        if (isset($data['id']) && $data['id']) {
            $devolucion = Devolucion::findOrFail($data['id']);
            $this->manejarCambiosInventario($devolucion, $data);
        } else {
            $devolucion = new Devolucion();
        }

        $devolucion->fill($data);
        $devolucion->save();

        return $devolucion;
    }

    /**
     * Procesa devolución completa (facturación)
     *
     * @param array $data
     * @return Devolucion
     */
    public function procesarDevolucion(array $data): Devolucion
    {
        DB::beginTransaction();
        
        try {
            // Crear o actualizar devolución
            if (isset($data['id']) && $data['id']) {
                $devolucion = Devolucion::findOrFail($data['id']);
            } else {
                $devolucion = new Devolucion();
            }

            $devolucion->fill($data);
            $devolucion->save();

            // Procesar detalles
            if (isset($data['detalles']) && is_array($data['detalles'])) {
                foreach ($data['detalles'] as $det) {
                    $detalle = new Detalle();
                    $det['id_devolucion_compra'] = $devolucion->id;
                    $detalle->fill($det);
                    $detalle->save();

                    // Actualizar inventario si el tipo afecta inventario
                    if (isset($data['tipo']) && $data['tipo'] !== 'descuento_ajuste') {
                        $this->actualizarInventarioPorDevolucion(
                            $det['id_producto'],
                            $data['id_bodega'],
                            $det['cantidad'],
                            $devolucion
                        );
                    }
                }
            }

            DB::commit();
            return $devolucion;

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error procesando devolución de compra', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $data
            ]);
            throw $e;
        }
    }

    /**
     * Maneja cambios de inventario al actualizar devolución
     *
     * @param Devolucion $devolucion
     * @param array $nuevosDatos
     * @return void
     */
    protected function manejarCambiosInventario(Devolucion $devolucion, array $nuevosDatos): void
    {
        // Solo ajustar stocks si el tipo de devolución afecta inventario
        if (isset($nuevosDatos['tipo']) && $nuevosDatos['tipo'] === 'descuento_ajuste') {
            return;
        }

        $estadoAnterior = $devolucion->enable;
        $estadoNuevo = $nuevosDatos['enable'] ?? $devolucion->enable;

        // Si cambió el estado, ajustar inventario
        if ($estadoAnterior != $estadoNuevo) {
            foreach ($devolucion->detalles as $detalle) {
                $inventario = Inventario::where('id_producto', $detalle->id_producto)
                    ->where('id_bodega', $devolucion->id_bodega)
                    ->first();

                if (!$inventario) {
                    continue;
                }

                // Anular y regresar stock
                if ($estadoAnterior != '0' && $estadoNuevo == '0') {
                    $inventario->stock += $detalle->cantidad;
                    $inventario->save();
                    $inventario->kardex($devolucion, $detalle->cantidad * -1);
                }
                // Cancelar anulación y descargar stock
                elseif ($estadoAnterior == '0' && $estadoNuevo != '0') {
                    $inventario->stock -= $detalle->cantidad;
                    $inventario->save();
                    $inventario->kardex($devolucion, $detalle->cantidad);
                }
            }
        }
    }

    /**
     * Actualiza inventario por devolución
     *
     * @param int $idProducto
     * @param int $idBodega
     * @param float $cantidad
     * @param Devolucion $devolucion
     * @return void
     */
    protected function actualizarInventarioPorDevolucion(
        int $idProducto,
        int $idBodega,
        float $cantidad,
        Devolucion $devolucion
    ): void {
        $inventario = Inventario::where('id_producto', $idProducto)
            ->where('id_bodega', $idBodega)
            ->first();

        if ($inventario) {
            $inventario->stock -= $cantidad;
            $inventario->save();
            $inventario->kardex($devolucion, $cantidad);
        }
    }

    /**
     * Elimina una devolución
     *
     * @param int $id
     * @return Devolucion
     */
    public function eliminarDevolucion(int $id): Devolucion
    {
        $devolucion = Devolucion::where('id', $id)->with('detalles')->firstOrFail();
        
        foreach ($devolucion->detalles as $detalle) {
            $detalle->delete();
        }
        
        $devolucion->delete();
        return $devolucion;
    }
}

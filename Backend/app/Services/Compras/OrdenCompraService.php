<?php

namespace App\Services\Compras;

use App\Models\Compras\Compra;
use App\Models\OrdenCompra;
use App\Models\OrdenCompraDetalle;
use App\Models\Authorization\AuthorizationType;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class OrdenCompraService
{
    /**
     * Actualiza la orden de compra relacionada cuando se factura una compra
     *
     * @param Compra $compra La compra que se está facturando
     * @param array $detalles Los detalles de la compra (array de detalles con id_producto y cantidad)
     * @return void
     */
    public function actualizarDesdeCompra(Compra $compra, array $detalles): void
    {
        // Si la compra no tiene orden de compra asociada, no hacer nada
        if (!$compra->num_orden_compra) {
            return;
        }

        Log::info("Actualizando orden de compra desde facturación", [
            'compra_id' => $compra->id,
            'orden_compra_id' => $compra->num_orden_compra
        ]);

        // Obtener la orden de compra con sus detalles
        $orden = OrdenCompra::where('id', $compra->num_orden_compra)
            ->with('detalles')
            ->first();

        if (!$orden) {
            Log::warning("Orden de compra no encontrada", [
                'orden_compra_id' => $compra->num_orden_compra
            ]);
            return;
        }

        // Convertir detalles a colección para facilitar búsquedas
        $detCollection = collect($detalles);
        
        // Flag para determinar si la orden está completa
        $finishOrder = true;

        // Actualizar cantidad procesada en cada detalle de la orden
        foreach ($orden->detalles as $detalleOrden) {
            // Buscar el detalle correspondiente en la compra por id_producto
            $detalleCompra = $detCollection->where('id_producto', $detalleOrden->id_producto)->first();

            if ($detalleCompra) {
                // Incrementar cantidad procesada
                $cantidadProcesadaAnterior = $detalleOrden->cantidad_procesada ?? 0;
                $detalleOrden->cantidad_procesada = $cantidadProcesadaAnterior + $detalleCompra['cantidad'];
                $detalleOrden->save();

                Log::info("Detalle de orden actualizado", [
                    'detalle_orden_id' => $detalleOrden->id,
                    'id_producto' => $detalleOrden->id_producto,
                    'cantidad_anterior' => $cantidadProcesadaAnterior,
                    'cantidad_agregada' => $detalleCompra['cantidad'],
                    'cantidad_procesada_nueva' => $detalleOrden->cantidad_procesada,
                    'cantidad_total' => $detalleOrden->cantidad
                ]);

                // Verificar si este detalle está completo
                if ($detalleOrden->cantidad_procesada < $detalleOrden->cantidad) {
                    $finishOrder = false;
                }
            }
        }

        // Verificar si todos los productos de la orden están en la compra
        // Si el número de detalles de la compra no coincide con el de la orden, no está completa
        if ($detCollection->count() != $orden->detalles->count()) {
            $finishOrder = false;
        }

        // Si la orden está completa, marcarla como 'Aceptada'
        if ($finishOrder) {
            $orden->estado = 'Aceptada';
            Log::info("Orden de compra marcada como aceptada", [
                'orden_compra_id' => $orden->id
            ]);
        }

        $orden->save();
    }

    /**
     * Calcula el total de una orden de compra desde los datos del request
     *
     * @param \Illuminate\Http\Request $request
     * @return float Total calculado
     */
    public function calcularTotalOrden($request): float
    {
        $total = $request->total ?? $request->sub_total ?? 0;

        // Si no hay total, calcularlo de los detalles
        if ($total == 0 && isset($request->detalles)) {
            $total = collect($request->detalles)->sum('total');
        }

        return (float) $total;
    }

    /**
     * Determina el tipo de autorización requerida según el monto
     *
     * @param float $total Total de la orden
     * @return string|null Tipo de autorización o null si no requiere
     */
    public function determinarTipoAutorizacion(float $total): ?string
    {
        if ($total >= 5000) {
            return 'orden_compra_nivel_3'; // Mayor a $5,000
        } elseif ($total >= 300) {
            return 'orden_compra_nivel_2'; // $300 - $4,999
        } elseif ($total > 0) {
            return 'orden_compra_nivel_1'; // $0 - $300
        }

        return null; // No requiere autorización
    }

    /**
     * Verifica si una orden requiere autorización y valida roles excluidos
     *
     * @param \Illuminate\Http\Request $request
     * @return array Resultado de la validación
     */
    public function validarAutorizacionRequerida($request): array
    {
        // No requiere autorización si es una actualización o ya tiene autorización
        if ($request->id || $request->id_authorization) {
            return [
                'requires_authorization' => false,
                'ok' => true
            ];
        }

        $total = $this->calcularTotalOrden($request);
        $authType = $this->determinarTipoAutorizacion($total);

        // Si no requiere autorización
        if (!$authType) {
            return [
                'requires_authorization' => false,
                'ok' => true
            ];
        }

        // Verificar si el usuario está excluido de autorización por rol
        $authTypeModel = AuthorizationType::where('name', $authType)->first();

        if ($authTypeModel && $authTypeModel->conditions) {
            $excludeRoles = $authTypeModel->conditions['exclude_roles'] ?? [];
            $user = Auth::user();

            // Cargar roles del usuario si no están cargados
            if (!$user->relationLoaded('roles')) {
                $user->load('roles');
            }

            // Verificar si el usuario tiene algún rol excluido
            $userRoles = $user->roles->pluck('name')->toArray();
            $isExcluded = !empty(array_intersect($userRoles, $excludeRoles));

            if ($isExcluded) {
                Log::info("Usuario excluido de autorización por rol - Usuario: " . $user->id . " - Roles: " . implode(', ', $userRoles));
                return [
                    'requires_authorization' => false,
                    'ok' => true
                ];
            }
        }

        Log::info("Orden de compra requiere autorización - Total: $" . $total . " - Tipo: " . $authType);

        return [
            'requires_authorization' => true,
            'ok' => false,
            'authorization_type' => $authType,
            'message' => "Esta orden de compra de $" . number_format($total, 2) . " requiere autorización",
            'total' => $total
        ];
    }

    /**
     * Crea o actualiza una orden de compra
     *
     * @param \Illuminate\Http\Request $request
     * @return OrdenCompra
     */
    public function crearOActualizarOrden($request): OrdenCompra
    {
        if ($request->id) {
            $orden = OrdenCompra::findOrFail($request->id);
        } else {
            $orden = new OrdenCompra;
            $orden->estado = "Pendiente";
        }

        // Validar cambio de estado
        if ($orden->estado == "Aceptada" && $request->estado == "Pendiente") {
            throw new \Exception("No se puede cambiar el estado de una cotización aceptada a pendiente");
        }

        // Validar anulación
        if ($request->estado == "Anulada") {
            $existCompras = Compra::where("num_orden_compra", $orden->id)
                ->where("estado", "!=", "Anulada")
                ->exists();
            
            if ($existCompras) {
                throw new \Exception("No se puede anular una cotización que ya tiene compras asociadas");
            }
        }

        // Llenar y guardar orden
        $orden->fill($request->merge([
            "id_empresa" => Auth::user()->id_empresa,
        ])->all());
        $orden->save();

        // Procesar detalles
        $deleted_detalles = $orden->detalles->pluck("id")->diff(collect($request->detalles ?? [])->pluck("id"));

        foreach (($request->detalles ?? []) as $_detalle) {
            if (isset($_detalle["id"])) {
                $detalle = OrdenCompraDetalle::find($_detalle["id"]);
            } else {
                $detalle = new OrdenCompraDetalle();
                $detalle->id_orden_compra = $orden->id;
            }

            $detalle->fill($_detalle);
            $detalle->save();
        }

        // Eliminar detalles removidos
        if ($deleted_detalles->isNotEmpty()) {
            OrdenCompraDetalle::whereIn("id", $deleted_detalles)->delete();
        }

        return $orden;
    }

    /**
     * Maneja la creación de una orden pendiente de autorización
     *
     * @param array $data Datos de la orden
     * @param \App\Models\Authorization\Authorization $authorization Autorización asociada
     * @return array Respuesta con la orden creada
     */
    public function handlePendingAuthorization(array $data, $authorization): array
    {
        Log::info("Creando orden de compra pendiente de autorización");

        DB::beginTransaction();

        try {
            // Crear orden en estado pendiente
            $ordenData = $data;
            $ordenData['estado'] = 'Pendiente Autorización';
            $ordenData['id_authorization'] = $authorization->id;
            $ordenData['id_sucursal'] = Auth::user()->id_sucursal;
            $ordenData['id_empresa'] = Auth::user()->id_empresa;

            $orden = new OrdenCompra;
            $orden->fill($ordenData);
            $orden->save();

            // Crear detalles de la orden pendiente
            foreach ($data['detalles'] ?? [] as $det) {
                $detalle = new OrdenCompraDetalle;
                $det['id_orden_compra'] = $orden->id;
                $detalle->fill($det);
                $detalle->save();
            }

            // Actualizar la autorización con el ID de la orden creada
            $authorization->update([
                'authorizeable_id' => $orden->id
            ]);

            DB::commit();

            return [
                'ok' => true,
                'data' => $orden,
                'estado' => 'Pendiente Autorización',
                'requires_authorization' => true,
                'authorization_code' => $authorization->code,
                'message' => 'Orden de compra creada pendiente de autorización'
            ];

        } catch (\Exception $e) {
            DB::rollback();
            Log::error("Error creando orden pendiente: " . $e->getMessage());

            return [
                'ok' => false,
                'requires_authorization' => true,
                'authorization_type' => $authorization->authorizationType->name ?? null,
                'message' => 'Error al crear orden pendiente: ' . $e->getMessage(),
                'authorization_code' => $authorization->code
            ];
        }
    }

    /**
     * Procesa una orden de compra autorizada
     *
     * @param int $ordenId ID de la orden
     * @return OrdenCompra
     */
    public function procesarOrdenAutorizada(int $ordenId): OrdenCompra
    {
        Log::info("Procesando orden de compra autorizada: " . $ordenId);

        DB::beginTransaction();

        try {
            $orden = OrdenCompra::findOrFail($ordenId);

            // Cambiar estado a aprobada
            $orden->estado = 'Aprobada';
            $orden->save();

            DB::commit();

            Log::info("Orden de compra autorizada procesada exitosamente: " . $ordenId);

            return $orden;

        } catch (\Exception $e) {
            DB::rollback();
            Log::error("Error procesando orden de compra autorizada: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Elimina una orden de compra y sus detalles
     *
     * @param int $id ID de la orden
     * @return OrdenCompra
     * @throws \Exception
     */
    public function eliminarOrdenCompra(int $id): OrdenCompra
    {
        $orden = OrdenCompra::findOrFail($id);

        // Validar que no tenga compras asociadas
        $tieneCompras = Compra::where('num_orden_compra', $id)
            ->where('estado', '!=', 'Anulada')
            ->exists();

        if ($tieneCompras) {
            throw new \Exception('No se puede eliminar una orden de compra que tiene compras asociadas');
        }

        DB::beginTransaction();

        try {
            // Eliminar detalles
            $orden->detalles()->delete();

            // Eliminar orden
            $orden->delete();

            DB::commit();

            Log::info("Orden de compra eliminada: " . $id);

            return $orden;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error eliminando orden de compra: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Construye query base para listar órdenes de compra con filtros
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function construirQueryConFiltros($request)
    {
        $query = OrdenCompra::query();

        // Filtros de búsqueda
        if ($request->buscador) {
            $query->where(function ($q) use ($request) {
                $q->where('estado', 'like', '%' . $request->buscador . '%')
                    ->orWhere('observaciones', 'like', '%' . $request->buscador . '%')
                    ->orWhere('forma_pago', 'like', '%' . $request->buscador . '%');
            });
        }

        // Filtros de fecha
        if ($request->inicio && $request->fin) {
            $query->whereBetween('fecha', [$request->inicio, $request->fin]);
        }

        // Filtros específicos
        $filtros = [
            'id_sucursal',
            'id_usuario',
            'id_proveedor',
            'forma_pago',
            'id_canal',
            'id_documento',
            'estado',
            'metodo_pago',
            'tipo_documento'
        ];

        foreach ($filtros as $filtro) {
            if ($request->has($filtro)) {
                $query->where($filtro, $request->$filtro);
            }
        }

        // Ordenamiento
        $orden = $request->orden ?? 'fecha';
        $direccion = $request->direccion ?? 'desc';
        $query->orderBy($orden, $direccion);
        $query->orderBy('id', 'desc');

        return $query;
    }

    /**
     * Obtiene órdenes de compra con paginación
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function listarOrdenes($request)
    {
        $query = $this->construirQueryConFiltros($request);
        return $query->paginate($request->paginate ?? 10);
    }

    /**
     * Busca órdenes de compra por texto
     *
     * @param string $texto Texto a buscar
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function buscarOrdenes(string $texto)
    {
        return OrdenCompra::where(function ($q) use ($texto) {
            $q->whereHas('proveedor', function ($query) use ($texto) {
                $query->where('nombre', 'like', '%' . $texto . '%');
            })
            ->orWhere('estado', 'like', '%' . $texto . '%');
        })
        ->with('proveedor')
        ->paginate(10);
    }

    /**
     * Obtiene órdenes de compra del vendedor autenticado
     *
     * @param int $usuarioId ID del usuario
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function obtenerOrdenesPorVendedor(int $usuarioId)
    {
        return OrdenCompra::where('id_usuario', $usuarioId)
            ->orderBy('id', 'desc')
            ->paginate(10);
    }

    /**
     * Busca órdenes de compra del vendedor por texto
     *
     * @param int $usuarioId ID del usuario
     * @param string $texto Texto a buscar
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function buscarOrdenesPorVendedor(int $usuarioId, string $texto)
    {
        return OrdenCompra::where('id_usuario', $usuarioId)
            ->where(function ($q) use ($texto) {
                $q->whereHas('proveedor', function ($query) use ($texto) {
                    $query->where('nombre', 'like', '%' . $texto . '%');
                })
                ->orWhere('estado', 'like', '%' . $texto . '%');
            })
            ->with('proveedor')
            ->paginate(10);
    }

    /**
     * Obtiene solicitudes de cotización para empresas con licencia
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\User $user Usuario autenticado
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     * @throws \Exception Si el usuario no tiene licencia
     */
    public function obtenerSolicitudes($request, $user)
    {
        $licencia = $user->empresa()->first()->licencia()->first();
        
        if (!$licencia) {
            throw new \Exception('No tienes una licencia');
        }

        $empresaPadre = $licencia->empresa()->first();
        $empresasLicencia = $licencia->empresas()->pluck('id_empresa')->toArray();

        $query = Compra::withoutGlobalScope('empresa')
            ->whereIn('id_empresa', $empresasLicencia)
            ->whereHas('proveedor', function ($query) use ($empresaPadre) {
                return $query->withoutGlobalScope('empresa')
                    ->where(function ($q) use ($empresaPadre) {
                        $q->where('nit', $empresaPadre->nit)
                            ->orWhere('ncr', $empresaPadre->ncr);
                    });
            })
            ->where('estado', 'Pendiente')
            ->where('cotizacion', 1)
            ->with(['proveedor' => function ($query) {
                $query->withoutGlobalScope('empresa');
            }]);

        // Aplicar filtros de búsqueda
        if ($request->buscador) {
            $query->where(function ($q) use ($request) {
                $q->where('correlativo', 'like', '%' . $request->buscador . '%')
                    ->orWhere('estado', 'like', '%' . $request->buscador . '%')
                    ->orWhere('observaciones', 'like', '%' . $request->buscador . '%')
                    ->orWhere('forma_pago', 'like', '%' . $request->buscador . '%');
            });
        }

        // Aplicar otros filtros
        $filtros = [
            'inicio' => function ($query, $request) {
                if ($request->inicio && $request->fin) {
                    return $query->whereBetween('fecha', [$request->inicio, $request->fin]);
                }
            },
            'id_sucursal',
            'id_usuario',
            'id_proveedor',
            'forma_pago',
            'id_canal',
            'id_documento',
            'estado',
            'metodo_pago',
            'tipo_documento'
        ];

        foreach ($filtros as $key => $filtro) {
            if (is_callable($filtro)) {
                $filtro($query, $request);
            } elseif ($request->has($filtro)) {
                $query->where($filtro, $request->$filtro);
            }
        }

        // Ordenamiento
        $orden = $request->orden ?? 'id';
        $direccion = $request->direccion ?? 'desc';
        $query->orderBy($orden, $direccion);
        $query->orderBy('id', 'desc');

        return $query->paginate($request->paginate ?? 10);
    }
}


<?php

namespace App\Services\Compras;

use App\Models\Compras\Compra;
use App\Models\OrdenCompra;
use App\Models\OrdenCompraDetalle;
use App\Models\Authorization\AuthorizationType;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;

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
     * Flujo activo solo si el tipo está habilitado y hay aprobadores en la empresa del usuario.
     * ponytail: sin aprobadores locales no bloqueamos OC (p. ej. empresa hija sin configurar).
     */
    public function debeSolicitarAutorizacionOrdenCompra(?string $authType, User $user): bool
    {
        if (!$authType) {
            return false;
        }

        $type = AuthorizationType::where('name', $authType)->where('active', true)->first();
        if (!$type) {
            return false;
        }

        return $type->users()->where('users.id_empresa', $user->id_empresa)->exists();
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

        $user = Auth::user();
        if (!$user || !$this->debeSolicitarAutorizacionOrdenCompra($authType, $user)) {
            return [
                'requires_authorization' => false,
                'ok' => true
            ];
        }

        // Verificar si el usuario está excluido de autorización por rol
        $authTypeModel = AuthorizationType::where('name', $authType)->first();

        if ($authTypeModel && $authTypeModel->conditions) {
            $excludeRoles = $authTypeModel->conditions['exclude_roles'] ?? [];

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
            // Evitar where('estado','') u otros vacíos: has() es true con string vacío en query params.
            if ($request->filled($filtro)) {
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
    /**
     * Compras-cotización visibles como solicitud cross-empresa (licencia).
     *
     * @throws \Exception
     */
    /**
     * @return array{licencia: \Illuminate\Database\Eloquent\Model, empresa_padre: \App\Models\Admin\Empresa, empresas_ids: int[]}
     */
    private function contextoSolicitudesLicencia($user): array
    {
        $licencia = $user->empresa()->first()->licencia()->first();

        if (!$licencia) {
            throw new \Exception('No tienes una licencia');
        }

        return [
            'licencia' => $licencia,
            'empresa_padre' => $licencia->empresa()->first(),
            'empresas_ids' => $licencia->empresas()->pluck('id_empresa')->toArray(),
        ];
    }

    private function scopeProveedorCoincideEmpresaPadre($query, $empresaPadre): void
    {
        $nitPadre = trim((string) ($empresaPadre->nit ?? ''));
        $ncrPadre = trim((string) ($empresaPadre->ncr ?? ''));

        $query->whereHas('proveedor', function ($query) use ($nitPadre, $ncrPadre) {
            $query->withoutGlobalScope('empresa')
                ->where(function ($q) use ($nitPadre, $ncrPadre) {
                    $aplico = false;
                    if ($nitPadre !== '') {
                        $q->where('nit', $nitPadre);
                        $aplico = true;
                    }
                    if ($ncrPadre !== '') {
                        $aplico ? $q->orWhere('ncr', $ncrPadre) : $q->where('ncr', $ncrPadre);
                        $aplico = true;
                    }
                    if (!$aplico) {
                        $q->whereRaw('0 = 1');
                    }
                });
        });
    }

    private function queryComprasSolicitudLicencia($user)
    {
        $ctx = $this->contextoSolicitudesLicencia($user);

        $query = Compra::withoutGlobalScope('empresa')
            ->whereIn('id_empresa', $ctx['empresas_ids'])
            ->where('cotizacion', 1);

        $this->scopeProveedorCoincideEmpresaPadre($query, $ctx['empresa_padre']);

        return $query;
    }

    private function queryOrdenesSolicitudLicencia($user)
    {
        $ctx = $this->contextoSolicitudesLicencia($user);

        $query = OrdenCompra::withoutGlobalScope('empresa')
            ->whereIn('id_empresa', $ctx['empresas_ids']);

        $this->scopeProveedorCoincideEmpresaPadre($query, $ctx['empresa_padre']);

        return $query;
    }

    private function aplicarFiltrosSolicitud($query, $request, string $origen): void
    {
        if ($request->buscador) {
            $buscador = $request->buscador;
            $query->where(function ($q) use ($buscador, $origen) {
                if ($origen === 'compra') {
                    $q->where('correlativo', 'like', '%' . $buscador . '%')
                        ->orWhere('estado', 'like', '%' . $buscador . '%')
                        ->orWhere('observaciones', 'like', '%' . $buscador . '%')
                        ->orWhere('forma_pago', 'like', '%' . $buscador . '%');
                } else {
                    $q->where('referencia', 'like', '%' . $buscador . '%')
                        ->orWhere('estado', 'like', '%' . $buscador . '%')
                        ->orWhere('observaciones', 'like', '%' . $buscador . '%');
                }
            });
        }

        if ($request->inicio && $request->fin) {
            $query->whereBetween('fecha', [$request->inicio, $request->fin]);
        }

        $columnas = ['id_sucursal', 'id_usuario', 'id_proveedor', 'estado'];
        if ($origen === 'compra') {
            $columnas = array_merge($columnas, [
                'forma_pago',
                'id_canal',
                'id_documento',
                'metodo_pago',
                'tipo_documento',
            ]);
        }

        foreach ($columnas as $columna) {
            $valor = $request->input($columna);
            if ($valor !== null && $valor !== '') {
                $query->where($columna, $valor);
            }
        }
    }

    private function mapCompraSolicitudItem(Compra $compra): array
    {
        $compra->loadMissing(['proveedor', 'empresa', 'usuario']);

        return [
            'id' => $compra->id,
            'fecha' => $compra->fecha,
            'empresa_nombre' => $compra->empresa?->nombre ?? '',
            'nombre_usuario' => $compra->nombre_usuario,
            'proveedor' => $compra->proveedor,
            'referencia' => $compra->referencia ?? $compra->correlativo,
            'estado' => $compra->estado,
            'total' => $compra->total,
            'id_empresa' => $compra->id_empresa,
            'origen_solicitud' => 'compra',
        ];
    }

    private function mapOrdenCompraSolicitudItem(OrdenCompra $orden): array
    {
        $orden->loadMissing(['proveedor', 'empresa', 'usuario']);

        return [
            'id' => $orden->id,
            'fecha' => $orden->fecha,
            'empresa_nombre' => $orden->empresa?->nombre ?? '',
            'nombre_usuario' => $orden->nombre_usuario,
            'proveedor' => $orden->proveedor,
            'referencia' => $orden->referencia,
            'estado' => $orden->estado,
            'total' => $orden->total,
            'id_empresa' => $orden->id_empresa,
            'origen_solicitud' => 'orden_compra',
        ];
    }

    public function buscarCompraSolicitudLicencia(int $id, $user): Compra
    {
        return $this->queryComprasSolicitudLicencia($user)
            ->where('id', $id)
            ->firstOrFail();
    }

    /**
     * Detalle de solicitud cross-empresa para facturar desde la empresa padre (compra-cotización u orden_compras).
     */
    public function obtenerSolicitudLicencia(int $id, $user): Compra|OrdenCompra
    {
        $compra = $this->queryComprasSolicitudLicencia($user)
            ->where('id', $id)
            ->with([
                'proveedor' => fn ($q) => $q->withoutGlobalScope('empresa'),
                'detalles',
                'empresa',
            ])
            ->first();

        if ($compra) {
            return $compra;
        }

        return $this->queryOrdenesSolicitudLicencia($user)
            ->where('id', $id)
            ->with([
                'proveedor' => fn ($q) => $q->withoutGlobalScope('empresa'),
                'detalles',
                'empresa',
            ])
            ->firstOrFail();
    }

    public function anularSolicitudCompra(int $id, $user): Compra|OrdenCompra
    {
        $compra = $this->queryComprasSolicitudLicencia($user)
            ->where('id', $id)
            ->first();

        if ($compra) {
            if ($compra->estado !== 'Pendiente') {
                throw new \Exception('Solo se pueden anular solicitudes pendientes');
            }
            $compra->estado = 'Anulada';
            $compra->save();

            return $compra;
        }

        $orden = $this->queryOrdenesSolicitudLicencia($user)
            ->where('id', $id)
            ->firstOrFail();

        if ($orden->estado !== 'Pendiente') {
            throw new \Exception('Solo se pueden anular solicitudes pendientes');
        }

        $orden->estado = 'Anulada';
        $orden->save();

        return $orden;
    }

    public function obtenerSolicitudes($request, $user): LengthAwarePaginator
    {
        $estado = $request->input('estado');
        if ($estado === null || $estado === '') {
            $estado = 'Pendiente';
        }

        $queryCompras = $this->queryComprasSolicitudLicencia($user)->where('estado', $estado);
        $this->aplicarFiltrosSolicitud($queryCompras, $request, 'compra');

        $queryOrdenes = $this->queryOrdenesSolicitudLicencia($user)->where('estado', $estado);
        $this->aplicarFiltrosSolicitud($queryOrdenes, $request, 'orden_compra');

        $compras = $queryCompras
            ->with(['proveedor' => fn ($q) => $q->withoutGlobalScope('empresa'), 'empresa', 'usuario'])
            ->get()
            ->map(fn (Compra $c) => $this->mapCompraSolicitudItem($c));

        $ordenes = $queryOrdenes
            ->with(['proveedor' => fn ($q) => $q->withoutGlobalScope('empresa'), 'empresa', 'usuario'])
            ->get()
            ->map(fn (OrdenCompra $o) => $this->mapOrdenCompraSolicitudItem($o));

        $ordenCampo = $request->orden ?? 'id';
        $direccion = strtolower((string) ($request->direccion ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        $merged = $compras->concat($ordenes)->sort(function ($a, $b) use ($ordenCampo, $direccion) {
            $va = $a[$ordenCampo] ?? $a['id'];
            $vb = $b[$ordenCampo] ?? $b['id'];
            $cmp = $va <=> $vb;
            if ($cmp === 0) {
                $cmp = ($a['id'] <=> $b['id']);
            }

            return $direccion === 'asc' ? $cmp : -$cmp;
        })->values();

        $perPage = (int) ($request->paginate ?? 10);
        $page = max(1, (int) ($request->page ?? 1));

        return new Paginator(
            $merged->forPage($page, $perPage)->values(),
            $merged->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );
    }
}


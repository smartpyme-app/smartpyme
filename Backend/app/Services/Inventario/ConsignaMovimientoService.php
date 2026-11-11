<?php

namespace App\Services\Inventario;

use App\Constants\ConsignaMovimientoTipoConstants;
use App\Constants\OrigenStockVentaConstants;
use App\Models\Admin\Empresa;
use App\Models\Compras\Compra;
use App\Models\Compras\Detalle as DetalleCompra;
use App\Models\Inventario\ConsignaMovimiento;
use App\Models\Inventario\Lote;
use App\Models\Ventas\Detalle as DetalleVenta;
use App\Models\Ventas\Venta;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ConsignaMovimientoService
{
    public function isLedgerActivo(int $idEmpresa): bool
    {
        $empresa = Empresa::query()->find($idEmpresa);

        return $empresa ? $empresa->isModuloConsignacionesLedgerActivo() : false;
    }

    /**
     * @param array{
     *   tipo: string,
     *   cantidad: float,
     *   id_producto: int,
     *   id_bodega: int,
     *   lote_id?: int|null,
     *   fecha?: string,
     *   id_proveedor?: int|null,
     *   id_cliente?: int|null,
     *   costo_unitario?: float|null,
     *   id_compra?: int|null,
     *   id_detalle_compra?: int|null,
     *   id_venta?: int|null,
     *   id_detalle_venta?: int|null,
     *   id_traslado?: int|null,
     *   nota?: string|null,
     *   idempotency_key?: string|null,
     *   id_usuario?: int|null,
     * } $payload
     */
    public function registrar(int $idEmpresa, array $payload): ?ConsignaMovimiento
    {
        if (! $this->isLedgerActivo($idEmpresa)) {
            return null;
        }

        $tipo = $payload['tipo'] ?? '';
        if (! ConsignaMovimientoTipoConstants::esValido($tipo)) {
            throw ValidationException::withMessages(['tipo' => 'Tipo de movimiento de consigna no válido.']);
        }

        $idempotencyKey = $payload['idempotency_key'] ?? null;
        if ($idempotencyKey) {
            $existente = ConsignaMovimiento::query()
                ->where('id_empresa', $idEmpresa)
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existente) {
                return $existente;
            }
        }

        $movimiento = new ConsignaMovimiento();
        $movimiento->id_empresa = $idEmpresa;
        $movimiento->tipo = $tipo;
        $movimiento->fecha = isset($payload['fecha'])
            ? Carbon::parse($payload['fecha'])
            : now();
        $movimiento->cantidad = (float) ($payload['cantidad'] ?? 0);
        $movimiento->id_producto = (int) $payload['id_producto'];
        $movimiento->id_bodega = (int) $payload['id_bodega'];
        $movimiento->lote_id = ! empty($payload['lote_id']) ? (int) $payload['lote_id'] : null;
        $movimiento->id_proveedor = $payload['id_proveedor'] ?? null;
        $movimiento->id_cliente = $payload['id_cliente'] ?? null;
        $movimiento->costo_unitario = $payload['costo_unitario'] ?? null;
        $movimiento->id_compra = $payload['id_compra'] ?? null;
        $movimiento->id_detalle_compra = $payload['id_detalle_compra'] ?? null;
        $movimiento->id_venta = $payload['id_venta'] ?? null;
        $movimiento->id_detalle_venta = $payload['id_detalle_venta'] ?? null;
        $movimiento->id_traslado = $payload['id_traslado'] ?? null;
        $movimiento->nota = $payload['nota'] ?? null;
        $movimiento->idempotency_key = $idempotencyKey;
        $movimiento->id_usuario = $payload['id_usuario'] ?? auth()->id();
        $movimiento->save();

        app(ConsignaCustodiaService::class)->aplicarDesdeMovimiento($movimiento);

        return $movimiento;
    }

    public function trasladoConsignaEntreBodegas(
        int $idEmpresa,
        int $idTraslado,
        int $idProducto,
        float $cantidadConsigna,
        int $idBodegaOrigen,
        int $idBodegaDestino,
        ?string $fecha = null
    ): void {
        if ($cantidadConsigna <= 0 || ! $this->isLedgerActivo($idEmpresa)) {
            return;
        }

        $base = [
            'id_producto' => $idProducto,
            'id_traslado' => $idTraslado,
            'fecha' => $fecha,
            'nota' => 'Traslado mercadería en consigna',
        ];

        $this->registrar($idEmpresa, array_merge($base, [
            'tipo' => ConsignaMovimientoTipoConstants::TRASLADO_CONSIGNA,
            'cantidad' => -$cantidadConsigna,
            'id_bodega' => $idBodegaOrigen,
            'idempotency_key' => 'traslado-' . $idTraslado . '-out-' . $idProducto,
        ]));

        $this->registrar($idEmpresa, array_merge($base, [
            'tipo' => ConsignaMovimientoTipoConstants::TRASLADO_CONSIGNA,
            'cantidad' => $cantidadConsigna,
            'id_bodega' => $idBodegaDestino,
            'idempotency_key' => 'traslado-' . $idTraslado . '-in-' . $idProducto,
        ]));
    }

    public function entradaProveedorDesdeCompra(
        Compra $compra,
        DetalleCompra $detalle,
        ?float $cantidadBase = null
    ): ?ConsignaMovimiento {
        if ($compra->estado !== 'Consigna') {
            return null;
        }

        return $this->registrar((int) $compra->id_empresa, [
            'tipo' => ConsignaMovimientoTipoConstants::ENTRADA_PROVEEDOR,
            'cantidad' => $cantidadBase ?? (float) $detalle->cantidad,
            'id_producto' => (int) $detalle->id_producto,
            'id_bodega' => (int) $compra->id_bodega,
            'lote_id' => $this->loteIdDesdeDetalleCompra($detalle),
            'id_proveedor' => $compra->id_proveedor,
            'costo_unitario' => (float) $detalle->costo,
            'id_compra' => $compra->id,
            'id_detalle_compra' => $detalle->id,
            'fecha' => $compra->fecha,
            'idempotency_key' => 'compra-entrada-' . $compra->id . '-det-' . $detalle->id,
        ]);
    }

    public function desdeVentaDetalle(Venta $venta, DetalleVenta $detalle): ?ConsignaMovimiento
    {
        $esSalidaConsigna = $venta->estado === 'Consigna';
        $esConsignaProveedor = OrigenStockVentaConstants::esConsignaCompra($detalle->origen_stock ?? null);

        if ($esSalidaConsigna && $esConsignaProveedor) {
            $tipo = ConsignaMovimientoTipoConstants::ENTREGA_CONSIGNA_PROVEEDOR;
        } elseif ($esSalidaConsigna) {
            $tipo = ConsignaMovimientoTipoConstants::ENTREGA_CONSIGNA_PROPIO;
        } elseif ($esConsignaProveedor) {
            $tipo = ConsignaMovimientoTipoConstants::VENTA_REGULAR_CONSIGNA_PROVEEDOR;
        } else {
            return null;
        }

        return $this->registrar((int) $venta->id_empresa, [
            'tipo' => $tipo,
            'cantidad' => -(float) $detalle->cantidad,
            'id_producto' => (int) $detalle->id_producto,
            'id_bodega' => (int) $venta->id_bodega,
            'lote_id' => $this->loteIdDesdeDetalleVenta($detalle),
            'id_cliente' => $venta->id_cliente,
            'id_venta' => $venta->id,
            'id_detalle_venta' => $detalle->id,
            'fecha' => $venta->fecha,
            'idempotency_key' => 'venta-' . $venta->id . '-det-' . $detalle->id,
        ]);
    }

    public function liquidacionProveedorDesdeCompra(
        Compra $compra,
        DetalleCompra $detalle,
        float $cantidad
    ): ?ConsignaMovimiento {
        if ($cantidad <= 0) {
            return null;
        }

        return $this->registrar((int) $compra->id_empresa, [
            'tipo' => ConsignaMovimientoTipoConstants::LIQUIDACION_PROVEEDOR,
            'cantidad' => -$cantidad,
            'id_producto' => (int) $detalle->id_producto,
            'id_bodega' => (int) $compra->id_bodega,
            'lote_id' => $this->loteIdDesdeDetalleCompra($detalle),
            'id_proveedor' => $compra->id_proveedor,
            'costo_unitario' => (float) $detalle->costo,
            'id_compra' => $compra->id,
            'id_detalle_compra' => $detalle->id,
            'fecha' => $compra->fecha,
            'idempotency_key' => 'compra-liq-' . $compra->id . '-det-' . $detalle->id,
        ]);
    }

    public function liquidacionClienteDesdeVenta(
        Venta $venta,
        DetalleVenta $detalle,
        float $cantidad
    ): ?ConsignaMovimiento {
        if ($cantidad <= 0) {
            return null;
        }

        return $this->registrar((int) $venta->id_empresa, [
            'tipo' => ConsignaMovimientoTipoConstants::LIQUIDACION_CLIENTE,
            'cantidad' => -$cantidad,
            'id_producto' => (int) $detalle->id_producto,
            'id_bodega' => (int) $venta->id_bodega,
            'lote_id' => $this->loteIdDesdeDetalleVenta($detalle),
            'id_cliente' => $venta->id_cliente,
            'id_venta' => $venta->id,
            'id_detalle_venta' => $detalle->id,
            'fecha' => $venta->fecha,
            'idempotency_key' => 'venta-liq-' . $venta->id . '-det-' . $detalle->id,
        ]);
    }

    /**
     * Lotes para el selector del kardex consigna: catálogo en bodega + lotes con movimientos en ledger.
     *
     * @return \Illuminate\Support\Collection<int, Lote>
     */
    public function lotesParaKardex(int $idEmpresa, int $idProducto, int $idBodega)
    {
        $desdeLedger = ConsignaMovimiento::query()
            ->forEmpresa($idEmpresa)
            ->where('id_producto', $idProducto)
            ->where('id_bodega', $idBodega)
            ->whereNotNull('lote_id')
            ->distinct()
            ->pluck('lote_id');

        $desdeDetalle = ConsignaMovimiento::query()
            ->forEmpresa($idEmpresa)
            ->where('id_producto', $idProducto)
            ->where('id_bodega', $idBodega)
            ->whereNull('lote_id')
            ->whereHas('detalleCompra', fn ($d) => $d->whereNotNull('lote_id'))
            ->with('detalleCompra:id,lote_id')
            ->get()
            ->pluck('detalleCompra.lote_id')
            ->filter();

        $ids = $desdeLedger->merge($desdeDetalle)->unique()->values();

        $catalogo = Lote::query()
            ->where('id_empresa', $idEmpresa)
            ->where('id_producto', $idProducto)
            ->where('id_bodega', $idBodega)
            ->pluck('id');

        $todosIds = $ids->merge($catalogo)->unique()->values();

        if ($todosIds->isEmpty()) {
            return collect();
        }

        return Lote::query()
            ->whereIn('id', $todosIds)
            ->orderBy('numero_lote')
            ->get(['id', 'numero_lote', 'stock', 'fecha_vencimiento']);
    }

    /**
     * @return Collection<int, ConsignaMovimiento>|LengthAwarePaginator
     */
    public function listar(Request $request, int $idEmpresa)
    {
        $query = ConsignaMovimiento::query()
            ->forEmpresa($idEmpresa)
            ->with([
                'producto:id,nombre,codigo',
                'bodega:id,nombre',
                'lote:id,numero_lote',
                'detalleCompra:id,lote_id',
                'detalleCompra.lote:id,numero_lote',
                'detalleVenta:id,lote_id',
                'detalleVenta.lote:id,numero_lote',
                'proveedor' => fn ($q) => $q->withoutGlobalScopes()->select(
                    'id', 'nombre', 'apellido', 'tipo', 'nombre_empresa', 'nombre_comercial', 'pais'
                ),
                'cliente' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'nombre', 'apellido'),
                'compra' => fn ($q) => $q->select('id', 'tipo_documento', 'referencia', 'id_empresa', 'id_proveedor')
                    ->with(['proveedor' => fn ($q) => $q->withoutGlobalScopes()->select(
                        'id', 'nombre', 'apellido', 'tipo', 'nombre_empresa', 'nombre_comercial', 'pais'
                    )]),
                'venta.documento:id,nombre',
                'venta.cliente' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'nombre', 'apellido', 'nombre_comercial', 'nombre_empresa'),
            ])
            ->orderByDesc('fecha')
            ->orderByDesc('id');

        if ($request->filled('fecha_ini') && $request->filled('fecha_fin')) {
            $query->whereBetween('fecha', [$request->fecha_ini, $request->fecha_fin]);
        }
        if ($request->filled('id_producto')) {
            $query->where('id_producto', (int) $request->id_producto);
        }
        if ($request->filled('id_bodega')) {
            $query->where('id_bodega', (int) $request->id_bodega);
        }
        if ($request->filled('lote_id')) {
            $loteId = (int) $request->lote_id;
            $idProductoFiltro = $request->filled('id_producto') ? (int) $request->id_producto : null;
            $query->where(function ($q) use ($loteId, $idProductoFiltro) {
                $q->where('lote_id', $loteId)
                    ->orWhere(function ($q2) use ($loteId) {
                        $q2->whereNull('lote_id')
                            ->where(function ($q3) use ($loteId) {
                                $q3->whereHas(
                                    'detalleCompra',
                                    fn ($d) => $d->where('lote_id', $loteId)
                                )->orWhereHas(
                                    'detalleVenta',
                                    fn ($d) => $d->where('lote_id', $loteId)
                                );
                            });
                    })
                    ->orWhere(function ($q2) use ($loteId, $idProductoFiltro) {
                        $q2->where('tipo', ConsignaMovimientoTipoConstants::LIQUIDACION_PROVEEDOR)
                            ->whereHas('compra.detalles', function ($d) use ($loteId, $idProductoFiltro) {
                                $d->where('lote_id', $loteId);
                                if ($idProductoFiltro) {
                                    $d->where('id_producto', $idProductoFiltro);
                                }
                            });
                    });
            });
        }
        if ($request->filled('id_proveedor')) {
            $query->where('id_proveedor', (int) $request->id_proveedor);
        }
        if ($request->filled('id_cliente')) {
            $query->where('id_cliente', (int) $request->id_cliente);
        }
        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        } else {
            $query->whereIn('tipo', ConsignaMovimientoTipoConstants::tiposKardexPoolProveedor());
        }

        $paginate = (int) ($request->paginate ?? 0);
        if ($paginate > 0) {
            $page = $query->paginate(min($paginate, 500));
            $page->setCollection(
                $page->getCollection()->map(fn (ConsignaMovimiento $m) => $this->enriquecerMovimientoParaApi($m))
            );

            return $page;
        }

        return $query->limit(5000)->get()->map(fn (ConsignaMovimiento $m) => $this->enriquecerMovimientoParaApi($m));
    }

    /** @return array<string, mixed> */
    public function enriquecerMovimientoParaApi(ConsignaMovimiento $m): array
    {
        $data = $m->toArray();
        $cantidad = (float) $m->cantidad;
        $data['tipo_etiqueta'] = ConsignaMovimientoTipoConstants::etiqueta($m->tipo);
        $data['tipo_ayuda'] = ConsignaMovimientoTipoConstants::ayuda($m->tipo);
        $data['documento'] = $this->documentoReferenciaMovimiento($m);
        $data['entrada_cantidad'] = $cantidad > 0 ? $cantidad : 0.0;
        $data['salida_cantidad'] = $cantidad < 0 ? abs($cantidad) : 0.0;
        $proveedor = $this->nombreProveedorMovimiento($m);
        $cliente = $this->nombreClienteMovimiento($m);
        $data['nombre_proveedor_origen'] = $proveedor;
        $data['nombre_cliente_movimiento'] = $cliente;
        $terceros = array_filter([
            $proveedor !== '—' ? $proveedor : null,
            $cliente,
        ]);
        $data['nombre_tercero'] = $terceros === [] ? '—' : implode(' / ', $terceros);
        $data['detalle_movimiento'] = $data['tipo_etiqueta'];
        $data['afecta_saldo_consigna'] = $m->tipo !== ConsignaMovimientoTipoConstants::LIQUIDACION_PROVEEDOR;
        $data['es_registro_contable'] = $m->tipo === ConsignaMovimientoTipoConstants::LIQUIDACION_PROVEEDOR;
        $data['numero_lote'] = $m->lote?->numero_lote
            ?? $m->detalleCompra?->lote?->numero_lote
            ?? $m->detalleVenta?->lote?->numero_lote;

        return $data;
    }

    private function loteIdDesdeDetalleCompra(DetalleCompra $detalle): ?int
    {
        if ($detalle->lote_id) {
            return (int) $detalle->lote_id;
        }

        return null;
    }

    private function loteIdDesdeDetalleVenta(DetalleVenta $detalle): ?int
    {
        if ($detalle->lote_id) {
            return (int) $detalle->lote_id;
        }

        return null;
    }

    private function nombreProveedorMovimiento(ConsignaMovimiento $m): string
    {
        $proveedor = $m->proveedor;
        if (! $proveedor && $m->relationLoaded('compra') && $m->compra?->relationLoaded('proveedor')) {
            $proveedor = $m->compra->proveedor;
        }
        if ($proveedor && method_exists($proveedor, 'nombreParaDocumento')) {
            $label = trim($proveedor->nombreParaDocumento());
            if ($label !== '') {
                return $label;
            }
        }
        if ($m->relationLoaded('compra') && $m->compra) {
            $desdeCompra = trim((string) ($m->compra->nombre_proveedor ?? ''));
            if ($desdeCompra !== '' && $desdeCompra !== 'Consumidor Final') {
                return $desdeCompra;
            }
        }

        return '—';
    }

    private function nombreClienteMovimiento(ConsignaMovimiento $m): ?string
    {
        $cliente = $m->cliente;
        if (! $cliente && $m->relationLoaded('venta') && $m->venta?->relationLoaded('cliente')) {
            $cliente = $m->venta->cliente;
        }
        if (! $cliente && $m->relationLoaded('venta') && $m->venta) {
            $nombreVenta = $m->venta->nombre_cliente ?? null;
            if ($nombreVenta && $nombreVenta !== '') {
                return $nombreVenta;
            }
        }
        if (! $cliente) {
            return null;
        }
        if (method_exists($cliente, 'nombreParaDocumento')) {
            return $cliente->nombreParaDocumento();
        }
        $partes = array_filter([$cliente->nombre ?? null, $cliente->apellido ?? null]);

        return $partes === [] ? null : implode(' ', $partes);
    }

    private function documentoReferenciaMovimiento(ConsignaMovimiento $m): ?string
    {
        if ($m->relationLoaded('venta') && $m->venta) {
            $nombreDoc = $m->venta->documento?->nombre ?? null;
            $corr = $m->venta->correlativo;
            $partes = array_filter([
                $nombreDoc,
                $corr !== null && $corr !== '' ? '#' . $corr : null,
            ]);

            return $partes === [] ? null : implode(' ', $partes);
        }
        if ($m->relationLoaded('compra') && $m->compra) {
            $partes = array_filter([
                $m->compra->tipo_documento ?? null,
                $m->compra->referencia ?? null,
            ]);

            return $partes === [] ? null : implode(' ', $partes);
        }

        return null;
    }
}

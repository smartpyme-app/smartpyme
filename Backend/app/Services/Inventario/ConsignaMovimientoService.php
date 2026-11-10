<?php

namespace App\Services\Inventario;

use App\Constants\ConsignaMovimientoTipoConstants;
use App\Constants\OrigenStockVentaConstants;
use App\Models\Admin\Empresa;
use App\Models\Compras\Compra;
use App\Models\Compras\Detalle as DetalleCompra;
use App\Models\Inventario\ConsignaMovimiento;
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
            $tipo = ConsignaMovimientoTipoConstants::VENTA_REGULAR_PROPIO;
        }

        return $this->registrar((int) $venta->id_empresa, [
            'tipo' => $tipo,
            'cantidad' => -(float) $detalle->cantidad,
            'id_producto' => (int) $detalle->id_producto,
            'id_bodega' => (int) $venta->id_bodega,
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
            'id_cliente' => $venta->id_cliente,
            'id_venta' => $venta->id,
            'id_detalle_venta' => $detalle->id,
            'fecha' => $venta->fecha,
            'idempotency_key' => 'venta-liq-' . $venta->id . '-det-' . $detalle->id,
        ]);
    }

    /**
     * @return Collection<int, ConsignaMovimiento>|LengthAwarePaginator
     */
    public function listar(Request $request, int $idEmpresa)
    {
        $query = ConsignaMovimiento::query()
            ->forEmpresa($idEmpresa)
            ->with(['producto:id,nombre,codigo', 'bodega:id,nombre', 'proveedor:id,nombre', 'cliente:id,nombre'])
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
        if ($request->filled('id_proveedor')) {
            $query->where('id_proveedor', (int) $request->id_proveedor);
        }
        if ($request->filled('id_cliente')) {
            $query->where('id_cliente', (int) $request->id_cliente);
        }
        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }

        $paginate = (int) ($request->paginate ?? 0);
        if ($paginate > 0) {
            return $query->paginate(min($paginate, 500));
        }

        return $query->limit(5000)->get();
    }
}

<?php

namespace App\Services\Inventario;

use App\Constants\ConsignaMovimientoTipoConstants;
use App\Constants\OrigenStockVentaConstants;
use App\Models\Compras\Compra;
use App\Models\Compras\Devoluciones\Devolucion as DevolucionCompra;
use App\Models\Ventas\Devoluciones\Devolucion as DevolucionVenta;
use App\Models\Ventas\Detalle as DetalleVenta;
use App\Models\Ventas\Venta;

class ConsignaDevolucionLedgerService
{
    public function __construct(
        private readonly ConsignaMovimientoService $movimientoService
    ) {}

    /**
     * Llamar después de sumar stock por devolución de venta (entrada física).
     */
    public function despuesEntradaFisicaDevolucionVenta(
        DevolucionVenta $devolucion,
        int $idProducto,
        float $cantidad
    ): void {
        if ($cantidad <= 0 || ! $devolucion->id_venta) {
            return;
        }

        $venta = Venta::query()->find($devolucion->id_venta);
        if (! $venta) {
            return;
        }

        $idEmpresa = (int) $venta->id_empresa;
        if (! $this->movimientoService->isLedgerActivo($idEmpresa)) {
            return;
        }

        $idBodega = (int) ($devolucion->id_bodega ?? $venta->id_bodega);
        $cliente = $venta->id_cliente;

        if ($venta->estado === 'Consigna' && $cliente) {
            $this->movimientoService->registrar($idEmpresa, [
                'tipo' => ConsignaMovimientoTipoConstants::DEVOLUCION_CLIENTE,
                'cantidad' => -$cantidad,
                'id_producto' => $idProducto,
                'id_bodega' => $idBodega,
                'id_cliente' => $cliente,
                'id_venta' => $venta->id,
                'fecha' => $devolucion->fecha,
                'nota' => 'Devolución venta #' . $devolucion->id,
                'idempotency_key' => 'dev-venta-cli-' . $devolucion->id . '-p-' . $idProducto,
            ]);

            return;
        }

        $detalleVenta = DetalleVenta::query()
            ->where('id_venta', $venta->id)
            ->where('id_producto', $idProducto)
            ->orderByDesc('id')
            ->first();

        if ($detalleVenta && OrigenStockVentaConstants::esConsignaCompra($detalleVenta->origen_stock ?? null)) {
            $this->movimientoService->registrar($idEmpresa, [
                'tipo' => ConsignaMovimientoTipoConstants::DEVOLUCION_PROVEEDOR,
                'cantidad' => $cantidad,
                'id_producto' => $idProducto,
                'id_bodega' => $idBodega,
                'id_venta' => $venta->id,
                'fecha' => $devolucion->fecha,
                'nota' => 'Devolución venta — repone pool consigna compra',
                'idempotency_key' => 'dev-venta-prov-' . $devolucion->id . '-p-' . $idProducto,
            ]);
        }
    }

    /**
     * Llamar después de descontar stock por devolución de compra (salida física).
     */
    public function despuesSalidaFisicaDevolucionCompra(
        DevolucionCompra $devolucion,
        int $idProducto,
        float $cantidad
    ): void {
        if ($cantidad <= 0) {
            return;
        }

        $compra = $devolucion->id_compra ? Compra::query()->find($devolucion->id_compra) : null;
        if (! $compra || ! $compra->es_consigna) {
            return;
        }

        $idEmpresa = (int) $compra->id_empresa;
        if (! $this->movimientoService->isLedgerActivo($idEmpresa)) {
            return;
        }

        $this->movimientoService->registrar($idEmpresa, [
            'tipo' => ConsignaMovimientoTipoConstants::DEVOLUCION_PROVEEDOR,
            'cantidad' => -$cantidad,
            'id_producto' => $idProducto,
            'id_bodega' => (int) $compra->id_bodega,
            'id_proveedor' => $compra->id_proveedor,
            'id_compra' => $compra->id,
            'fecha' => $devolucion->fecha,
            'nota' => 'Devolución compra consigna #' . $devolucion->id,
            'idempotency_key' => 'dev-compra-prov-' . $devolucion->id . '-p-' . $idProducto,
        ]);
    }
}

<?php

namespace App\Services\Inventario;

use App\Models\Compras\Detalle as DetalleCompra;
use App\Models\Inventario\Inventario;
use App\Models\Ventas\Detalle as DetalleVenta;
use Illuminate\Http\Request;

class ConsignaDisponibleService
{
    /**
     * Stock en consigna disponible para venta al cliente:
     * compras en consigna (proveedor) − ventas en consigna (cliente), por bodega.
     */
    public function calcularDisponible(int $idProducto, int $idBodega, ?int $excluirVentaId = null): float
    {
        $entrada = (float) DetalleCompra::query()
            ->where('id_producto', $idProducto)
            ->whereHas('compra', function ($query) use ($idBodega) {
                $query->where('estado', 'Consigna')
                    ->where('id_bodega', $idBodega)
                    ->where('cotizacion', 0);
            })
            ->sum('cantidad');

        $salidaQuery = DetalleVenta::query()
            ->where('id_producto', $idProducto)
            ->whereHas('venta', function ($query) use ($idBodega, $excluirVentaId) {
                $query->where('estado', 'Consigna')
                    ->where('id_bodega', $idBodega);
                if ($excluirVentaId) {
                    $query->where('id', '!=', $excluirVentaId);
                }
            });

        $salida = (float) $salidaQuery->sum('cantidad');

        $disponible = max(0, $entrada - $salida);

        $inventario = Inventario::query()
            ->where('id_producto', $idProducto)
            ->where('id_bodega', $idBodega)
            ->first();

        $stockFisico = $inventario ? (float) $inventario->stock : 0;

        return min($disponible, $stockFisico);
    }

    /**
     * Valida una venta por consigna. Retorna mensaje de error o null si es válida.
     * La consigna es el tipo de venta (toda la factura); no restringe productos por pool de compras.
     */
    public function validarVentaConsigna(Request $request): ?string
    {
        if ($request->estado !== 'Consigna') {
            return null;
        }

        if (!$request->id_cliente) {
            return 'El cliente es obligatorio para ventas por consigna.';
        }

        return null;
    }
}

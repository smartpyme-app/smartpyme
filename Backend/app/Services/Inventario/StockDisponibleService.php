<?php

namespace App\Services\Inventario;

use App\Models\Admin\Empresa;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Lote;
use App\Models\Inventario\Producto;

class StockDisponibleService
{
    /**
     * Stock disponible en unidades base para validar ventas y buscadores.
     * Con lotes activos: stock del lote indicado o suma de lotes en bodega.
     * Sin lotes: stock del registro de inventario tradicional.
     *
     * @return float|null null si es servicio o no hay inventario (productos sin lotes).
     */
    public static function obtenerParaVenta(
        Producto $producto,
        int $idBodega,
        ?Empresa $empresa,
        ?int $loteId = null
    ): ?float {
        if ($producto->tipo === 'Servicio') {
            return null;
        }

        $lotesActivo = $empresa && $empresa->isLotesActivo();

        if ($producto->inventario_por_lotes && $lotesActivo) {
            if ($loteId) {
                $lote = Lote::where('id', $loteId)
                    ->where('id_producto', $producto->id)
                    ->where('id_bodega', $idBodega)
                    ->first();

                return $lote ? (float) $lote->stock : 0.0;
            }

            return (float) Lote::where('id_producto', $producto->id)
                ->where('id_bodega', $idBodega)
                ->sum('stock');
        }

        $inventario = Inventario::where('id_producto', $producto->id)
            ->where('id_bodega', $idBodega)
            ->first();

        return $inventario ? (float) $inventario->stock : null;
    }
}

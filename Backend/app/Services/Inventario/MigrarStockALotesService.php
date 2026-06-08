<?php

namespace App\Services\Inventario;

use App\Models\Inventario\Inventario;
use App\Models\Inventario\Lote;
use App\Models\Inventario\Producto;

class MigrarStockALotesService
{
    public const NUMERO_LOTE_INICIAL = 'STOCK-INICIAL';

    /**
     * Vista previa de migración al activar inventario por lotes.
     */
    public function preview(Producto $producto): array
    {
        $bodegas = [];
        $totalUnidades = 0;

        foreach ($this->inventariosConStockPendiente($producto) as $inv) {
            $stock = (float) $inv->stock;
            $totalUnidades += $stock;
            $bodegas[] = [
                'id_bodega' => $inv->id_bodega,
                'nombre_bodega' => $inv->nombre_bodega,
                'stock_inventario' => $stock,
            ];
        }

        return [
            'requiere_migracion' => count($bodegas) > 0,
            'numero_lote' => self::NUMERO_LOTE_INICIAL,
            'bodegas' => $bodegas,
            'total_unidades' => round($totalUnidades, 2),
            'total_bodegas' => count($bodegas),
        ];
    }

    /**
     * Crea lotes STOCK-INICIAL con el stock actual de inventario (por bodega).
     * No modifica inventario.traditional — se mantiene sincronizado en ventas.
     */
    public function migrar(Producto $producto): array
    {
        $lotesCreados = 0;
        $lotesActualizados = 0;
        $unidadesMigradas = 0;
        $detalleBodegas = [];

        foreach ($this->inventariosConStockPendiente($producto) as $inv) {
            $stock = (float) $inv->stock;
            if ($stock <= 0) {
                continue;
            }

            $lote = Lote::withoutGlobalScopes()
                ->where('id_producto', $producto->id)
                ->where('id_bodega', $inv->id_bodega)
                ->where('numero_lote', self::NUMERO_LOTE_INICIAL)
                ->where('id_empresa', $producto->id_empresa)
                ->whereNull('deleted_at')
                ->first();

            if ($lote) {
                if ((float) $lote->stock <= 0) {
                    $lote->stock = $stock;
                    $lote->stock_inicial = $stock;
                    $lote->save();
                    $lotesActualizados++;
                    $unidadesMigradas += $stock;
                    $detalleBodegas[] = [
                        'id_bodega' => $inv->id_bodega,
                        'nombre_bodega' => $inv->nombre_bodega,
                        'stock' => $stock,
                        'accion' => 'actualizado',
                    ];
                }
                continue;
            }

            Lote::create([
                'id_producto' => $producto->id,
                'id_bodega' => $inv->id_bodega,
                'numero_lote' => self::NUMERO_LOTE_INICIAL,
                'stock' => $stock,
                'stock_inicial' => $stock,
                'id_empresa' => $producto->id_empresa,
                'observaciones' => 'Migración automática al activar inventario por lotes',
            ]);

            $lotesCreados++;
            $unidadesMigradas += $stock;
            $detalleBodegas[] = [
                'id_bodega' => $inv->id_bodega,
                'nombre_bodega' => $inv->nombre_bodega,
                'stock' => $stock,
                'accion' => 'creado',
            ];
        }

        return [
            'lotes_creados' => $lotesCreados,
            'lotes_actualizados' => $lotesActualizados,
            'unidades_migradas' => round($unidadesMigradas, 2),
            'bodegas' => $detalleBodegas,
        ];
    }

    /**
     * Inventarios con stock en bodegas donde los lotes aún no cubren ese stock.
     *
     * @return \Illuminate\Support\Collection<int, Inventario>
     */
    protected function inventariosConStockPendiente(Producto $producto)
    {
        return Inventario::where('id_producto', $producto->id)
            ->where('stock', '>', 0)
            ->with('bodega')
            ->get()
            ->filter(function (Inventario $inv) use ($producto) {
                $stockEnLotes = (float) Lote::withoutGlobalScopes()
                    ->where('id_producto', $producto->id)
                    ->where('id_bodega', $inv->id_bodega)
                    ->where('id_empresa', $producto->id_empresa)
                    ->whereNull('deleted_at')
                    ->sum('stock');

                return $stockEnLotes <= 0;
            })
            ->values();
    }
}

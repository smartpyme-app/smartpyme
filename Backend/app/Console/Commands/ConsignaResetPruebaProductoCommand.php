<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ConsignaResetPruebaProductoCommand extends Command
{
    protected $signature = 'consigna:reset-prueba-producto
                            {--empresa=855 : ID empresa}
                            {--producto=375530 : ID producto}
                            {--bodega=1147 : ID bodega / id_inventario kardex}
                            {--lote=2414 : ID lote (vacío = todos los lotes del producto en bodega)}
                            {--stock=100 : Stock objetivo en inventario y lote(s)}
                            {--force : Sin confirmación}';

    protected $description = 'Elimina ventas/compras/kardex/consigna de prueba de un producto y deja stock base';

    public function handle(): int
    {
        $idEmpresa = (int) $this->option('empresa');
        $idProducto = (int) $this->option('producto');
        $idBodega = (int) $this->option('bodega');
        $idLote = $this->option('lote') !== null && $this->option('lote') !== ''
            ? (int) $this->option('lote')
            : null;
        $stock = (float) $this->option('stock');

        $ventaIds = DB::table('detalles_venta as d')
            ->join('ventas as v', 'v.id', '=', 'd.id_venta')
            ->where('d.id_producto', $idProducto)
            ->where('v.id_empresa', $idEmpresa)
            ->distinct()
            ->pluck('v.id')
            ->all();

        $compraIds = DB::table('detalles_compra as d')
            ->join('compras as c', 'c.id', '=', 'd.id_compra')
            ->where('d.id_producto', $idProducto)
            ->where('c.id_empresa', $idEmpresa)
            ->distinct()
            ->pluck('c.id')
            ->all();

        $this->info(sprintf(
            'Ventas a eliminar: %d | Compras a eliminar: %d',
            count($ventaIds),
            count($compraIds)
        ));

        if (! $this->option('force') && ! $this->confirm('¿Continuar con el borrado y reset de stock?', true)) {
            return self::FAILURE;
        }

        DB::transaction(function () use (
            $idEmpresa,
            $idProducto,
            $idBodega,
            $idLote,
            $stock,
            $ventaIds,
            $compraIds
        ) {
            $consignaMov = DB::table('consigna_movimientos')
                ->where('id_empresa', $idEmpresa)
                ->where('id_producto', $idProducto)
                ->delete();
            $consignaCust = DB::table('consigna_custodias')
                ->where('id_empresa', $idEmpresa)
                ->where('id_producto', $idProducto)
                ->delete();

            if ($ventaIds !== []) {
                $detalleVentaIds = DB::table('detalles_venta')->whereIn('id_venta', $ventaIds)->pluck('id');
                if (Schema::hasTable('detalle_venta_lotes') && $detalleVentaIds->isNotEmpty()) {
                    DB::table('detalle_venta_lotes')->whereIn('id_detalle_venta', $detalleVentaIds)->delete();
                }
                DB::table('detalles_venta')->whereIn('id_venta', $ventaIds)->delete();
                DB::table('ventas')->whereIn('id', $ventaIds)->where('id_empresa', $idEmpresa)->delete();
            }

            if ($compraIds !== []) {
                $detalleCompraIds = DB::table('detalles_compra')->whereIn('id_compra', $compraIds)->pluck('id');
                if (Schema::hasTable('detalle_compra_lotes') && $detalleCompraIds->isNotEmpty()) {
                    DB::table('detalle_compra_lotes')->whereIn('id_detalle_compra', $detalleCompraIds)->delete();
                }
                DB::table('detalles_compra')->whereIn('id_compra', $compraIds)->delete();
                DB::table('compras')->whereIn('id', $compraIds)->where('id_empresa', $idEmpresa)->delete();
            }

            $kardex = DB::table('kardexs')
                ->where('id_producto', $idProducto)
                ->where('id_inventario', $idBodega)
                ->delete();

            DB::table('inventario')
                ->where('id_producto', $idProducto)
                ->where('id_bodega', $idBodega)
                ->update(['stock' => $stock]);

            $loteQuery = DB::table('lotes')
                ->where('id_producto', $idProducto)
                ->where('id_bodega', $idBodega);
            if ($idLote) {
                $loteQuery->where('id', $idLote);
            }
            $loteQuery->update([
                'stock' => $stock,
                'stock_inicial' => $stock,
            ]);

            $this->info("consigna_movimientos: {$consignaMov}");
            $this->info("consigna_custodias: {$consignaCust}");
            $this->info("kardexs: {$kardex}");
            $this->info("Stock inventario y lote(s) → {$stock}");
        });

        return self::SUCCESS;
    }
}

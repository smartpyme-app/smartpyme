<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddIdPaqueteToRestaurantePedidoDetallesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('restaurante_pedido_detalles', function (Blueprint $table) {
            $table->integer('id_paquete')->nullable()->after('producto_id');
            $table->foreign('id_paquete')
                ->references('id')
                ->on('paquetes')
                ->onDelete('set null');
        });

        // ponytail: backfill existing records by matching WR number in notas
        try {
            $detalles = DB::table('restaurante_pedido_detalles')
                ->whereNotNull('notas')
                ->where('notas', 'like', '%Número:%')
                ->get();

            foreach ($detalles as $det) {
                if (preg_match('/Número:\s*(\S+)/', $det->notas, $m)) {
                    $wr = $m[1];
                    $paquete = DB::table('paquetes')->where('wr', $wr)->first();
                    if ($paquete) {
                        DB::table('restaurante_pedido_detalles')
                            ->where('id', $det->id)
                            ->update(['id_paquete' => $paquete->id]);
                    }
                }
            }
        } catch (\Exception $e) {
            // Log or ignore backfill errors during migration so it doesn't block deployment
            Log::error('Error backfilling id_paquete in restaurante_pedido_detalles: ' . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('restaurante_pedido_detalles', function (Blueprint $table) {
            $table->dropForeign(['id_paquete']);
            $table->dropColumn('id_paquete');
        });
    }
}

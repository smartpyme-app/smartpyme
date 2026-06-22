<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOrigenStockToDetallesVentaTable extends Migration
{
    public function up()
    {
        Schema::table('detalles_venta', function (Blueprint $table) {
            $table->string('origen_stock', 32)->nullable()->after('lote_id');
        });
    }

    public function down()
    {
        Schema::table('detalles_venta', function (Blueprint $table) {
            $table->dropColumn('origen_stock');
        });
    }
}

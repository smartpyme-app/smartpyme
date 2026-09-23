<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddShopifySyncVentasToEmpresasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->boolean('shopify_sync_ventas')
                ->default(false)
                ->after('shopify_sync_bidirectional')
                ->comment('Controla si las ventas de SmartPyme se sincronizan como órdenes en Shopify');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn('shopify_sync_ventas');
        });
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUniqueIndexToReferenciaShopifyInVentasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('ventas', function (Blueprint $table) {
            // Agregar índice único compuesto para referencia_shopify e id_empresa
            $table->unique(['referencia_shopify', 'id_empresa'], 'unique_referencia_shopify_empresa');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ventas', function (Blueprint $table) {
            // Eliminar el índice único
            $table->dropUnique('unique_referencia_shopify_empresa');
        });
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIdPresentacionToProductoComposicionesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('producto_composiciones', function (Blueprint $table) {
            $table->unsignedBigInteger('id_presentacion')->nullable();
            
            $table->foreign('id_presentacion')
                  ->references('id')->on('producto_presentaciones')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('producto_composiciones', function (Blueprint $table) {
            $table->dropForeign(['id_presentacion']);
            $table->dropColumn('id_presentacion');
        });
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLoteIdToTrasladosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('traslados', function (Blueprint $table) {
            $table->unsignedInteger('lote_id')->nullable()->after('id_bodega_de');
            $table->unsignedInteger('lote_id_destino')->nullable()->after('lote_id');
            $table->foreign('lote_id')->references('id')->on('lotes')->onDelete('set null');
            $table->foreign('lote_id_destino')->references('id')->on('lotes')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('traslados', function (Blueprint $table) {
            $table->dropForeign(['lote_id']);
            $table->dropForeign(['lote_id_destino']);
            $table->dropColumn('lote_id');
            $table->dropColumn('lote_id_destino');
        });
    }
}

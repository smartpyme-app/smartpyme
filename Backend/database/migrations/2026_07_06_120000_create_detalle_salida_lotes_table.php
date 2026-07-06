<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDetalleSalidaLotesTable extends Migration
{
    public function up()
    {
        Schema::create('detalle_salida_lotes', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('id_detalle_salida');
            $table->unsignedInteger('lote_id');
            $table->decimal('cantidad', 12, 4);
            $table->timestamps();

            $table->foreign('id_detalle_salida')->references('id')->on('inventario_salida_detalles')->onDelete('cascade');
            $table->foreign('lote_id')->references('id')->on('lotes')->onDelete('restrict');
            $table->index(['id_detalle_salida', 'lote_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('detalle_salida_lotes');
    }
}

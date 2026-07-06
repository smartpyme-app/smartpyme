<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDetalleVentaLotesTable extends Migration
{
    public function up()
    {
        Schema::create('detalle_venta_lotes', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('id_detalle_venta');
            $table->unsignedInteger('lote_id');
            $table->decimal('cantidad', 12, 4);
            $table->timestamps();

            $table->foreign('id_detalle_venta')->references('id')->on('detalles_venta')->onDelete('cascade');
            $table->foreign('lote_id')->references('id')->on('lotes')->onDelete('restrict');
            $table->index(['id_detalle_venta', 'lote_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('detalle_venta_lotes');
    }
}

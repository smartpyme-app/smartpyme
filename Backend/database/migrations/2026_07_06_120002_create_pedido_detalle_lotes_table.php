<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePedidoDetalleLotesTable extends Migration
{
    public function up()
    {
        Schema::create('pedido_detalle_lotes', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('pedido_detalle_id');
            $table->unsignedInteger('lote_id');
            $table->decimal('cantidad', 12, 4);
            $table->timestamps();

            $table->foreign('pedido_detalle_id')->references('id')->on('restaurante_pedido_detalles')->onDelete('cascade');
            $table->foreign('lote_id')->references('id')->on('lotes')->onDelete('restrict');
            $table->index(['pedido_detalle_id', 'lote_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('pedido_detalle_lotes');
    }
}

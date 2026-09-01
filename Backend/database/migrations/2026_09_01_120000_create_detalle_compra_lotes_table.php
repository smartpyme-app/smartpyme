<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDetalleCompraLotesTable extends Migration
{
    public function up()
    {
        // Si un intento previo falló al crear el FK, la tabla puede existir a medias.
        Schema::dropIfExists('detalle_compra_lotes');

        Schema::create('detalle_compra_lotes', function (Blueprint $table) {
            $table->increments('id');
            // detalles_compra.id es INT signed (igual que detalles_venta en detalle_venta_lotes)
            $table->integer('id_detalle_compra');
            $table->unsignedInteger('lote_id');
            $table->decimal('cantidad', 12, 4);
            $table->timestamps();

            $table->foreign('id_detalle_compra')->references('id')->on('detalles_compra')->onDelete('cascade');
            $table->foreign('lote_id')->references('id')->on('lotes')->onDelete('restrict');
            $table->index(['id_detalle_compra', 'lote_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('detalle_compra_lotes');
    }
}

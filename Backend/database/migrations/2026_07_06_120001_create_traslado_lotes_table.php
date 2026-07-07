<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTrasladoLotesTable extends Migration
{
    public function up()
    {
        Schema::create('traslado_lotes', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('traslado_id');
            $table->unsignedInteger('lote_id');
            $table->unsignedInteger('lote_id_destino')->nullable();
            $table->decimal('cantidad', 12, 4);
            $table->timestamps();

            $table->foreign('traslado_id')->references('id')->on('traslados')->onDelete('cascade');
            $table->foreign('lote_id')->references('id')->on('lotes')->onDelete('restrict');
            $table->foreign('lote_id_destino')->references('id')->on('lotes')->onDelete('set null');
            $table->index(['traslado_id', 'lote_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('traslado_lotes');
    }
}

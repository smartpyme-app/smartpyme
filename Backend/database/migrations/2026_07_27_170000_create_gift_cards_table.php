<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGiftCardsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gift_cards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_empresa');
            $table->string('codigo', 64);
            $table->decimal('monto_inicial', 14, 4);
            $table->decimal('saldo', 14, 4);
            $table->timestamp('fecha_emision');
            $table->timestamp('fecha_vencimiento')->nullable();
            $table->unsignedBigInteger('id_vendedor_emisor')->nullable();
            $table->unsignedBigInteger('id_venta_emision');
            $table->unsignedBigInteger('id_detalle_venta_emision')->nullable();
            $table->unsignedBigInteger('id_producto')->nullable();
            $table->string('estado', 20)->default('activa'); // activa|agotada|anulada
            $table->timestamps();
            $table->unique(['id_empresa', 'codigo']);
            $table->unique(['id_empresa', 'id_detalle_venta_emision'], 'gift_card_unique_detalle_emision');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gift_cards');
    }
}

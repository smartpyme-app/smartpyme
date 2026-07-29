<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGiftCardRedencionesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gift_card_redenciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_empresa');
            $table->unsignedBigInteger('id_gift_card');
            $table->unsignedBigInteger('id_venta');
            $table->unsignedBigInteger('id_vendedor')->nullable();
            $table->decimal('monto', 14, 4);
            $table->decimal('saldo_resultante', 14, 4);
            $table->unsignedBigInteger('id_categoria')->nullable();
            $table->unsignedBigInteger('id_subcategoria')->nullable();
            $table->unsignedBigInteger('id_comision_movimiento')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gift_card_redenciones');
    }
}

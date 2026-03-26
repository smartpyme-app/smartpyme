<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateComandaDetalleRestauranteTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('comanda_detalle_restaurante', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comanda_id')->constrained('comandas_restaurante')->onDelete('cascade');
            $table->foreignId('orden_detalle_id')->constrained('orden_detalle_restaurante')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['comanda_id', 'orden_detalle_id'], 'comanda_orden_detalle_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('comanda_detalle_restaurante');
    }
}

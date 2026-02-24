<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateConsumoPuntosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('consumo_puntos', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_empresa')->constrained('empresas');
            $table->unsignedInteger('id_cliente')->constrained('clientes');
            $table->foreignId('id_canje_tx')->constrained('transacciones_puntos')->comment('tipo = canje');
            $table->foreignId('id_ganancia_tx')->constrained('transacciones_puntos')->comment('tipo = ganancia');
            $table->integer('puntos_consumidos')->comment('> 0');
            $table->text('descripcion')->nullable()->comment('opcional - para auditoría');
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
        Schema::dropIfExists('consumo_puntos');
    }
}

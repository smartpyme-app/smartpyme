<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePuntosClienteTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('puntos_cliente', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_cliente')->constrained('clientes');
            $table->foreignId('id_empresa')->constrained('empresas');
            $table->integer('puntos_disponibles')->default(0);
            $table->integer('puntos_totales_ganados')->default(0);
            $table->integer('puntos_totales_canjeados')->default(0);
            $table->timestamp('fecha_ultima_actividad')->nullable();
            $table->timestamps();
            
            // Un registro único por cliente-empresa
            $table->unique(['id_cliente', 'id_empresa']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('puntos_cliente');
    }
}

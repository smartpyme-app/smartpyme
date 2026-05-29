<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateComandasRestauranteTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('comandas_restaurante', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sesion_id')->constrained('restaurante_sesiones_mesa')->onDelete('cascade');
            $table->string('numero_comanda', 30)->comment('Número correlativo de comanda');
            $table->enum('estado', ['pendiente', 'preparando', 'listo'])->default('pendiente');
            $table->timestamp('enviado_at')->nullable();
            $table->timestamps();

            $table->index(['sesion_id', 'estado']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('comandas_restaurante');
    }
}

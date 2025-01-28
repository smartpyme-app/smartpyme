<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMetodosPagoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('metodos_pago', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_usuario')->constrained('users')->onDelete('cascade');
            $table->string('id_tarjeta');
            $table->string('marca_tarjeta', 50)->nullable();
            $table->string('ultimos_cuatro', 4)->nullable();
            $table->string('titular_tarjeta')->nullable();
            $table->string('nombre_emisor', 100)->nullable();
            $table->string('codigo_pais', 3)->nullable();
            $table->string('codigo_estado', 50)->nullable();
            $table->string('codigo_postal', 20)->nullable();
            $table->boolean('es_predeterminado')->default(false);
            $table->boolean('esta_activo')->default(true);
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
        Schema::dropIfExists('metodos_pago');
    }
}

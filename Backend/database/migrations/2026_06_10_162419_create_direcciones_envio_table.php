<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDireccionesEnvioTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('direcciones_envio', function (Blueprint $table) {
            $table->id();
            $table->integer('id_cliente');
            $table->unsignedInteger('id_empresa');
            
            $table->string('alias')->nullable(); // Ej. "Casa", "Oficina"
            $table->text('direccion');
            $table->text('referencia')->nullable();
            $table->string('telefono');
            $table->string('codigo_area')->default('503');
            
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 11, 7)->nullable();
            
            $table->unsignedInteger('boxful_state_id');
            $table->unsignedInteger('boxful_city_id');
            $table->string('boxful_address_id')->nullable(); // ID retornado por Boxful
            
            $table->boolean('es_predeterminada')->default(false);
            
            $table->timestamps();

            // Llaves foráneas
            $table->foreign('id_cliente')->references('id')->on('clientes')->onDelete('cascade');
            $table->foreign('id_empresa')->references('id')->on('empresas')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('direcciones_envio');
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDireccionesOrigenTable extends Migration
{
    public function up()
    {
        Schema::create('direcciones_origen', function (Blueprint $table) {
            $table->id();
            // ¡Ojo! Aquí NO va id_cliente, porque es la dirección de la empresa
            $table->unsignedInteger('id_empresa'); 
            
            $table->string('alias'); // Ej. "Bodega Central", "Sucursal Norte", "Tienda Centro"
            $table->string('nombre_contacto')->nullable(); // Persona responsable de recibir al courier
            $table->text('direccion');        // Equivale a 'address' en Boxful
            $table->text('referencia');       // Equivale a 'referencePoint' en Boxful
            $table->string('telefono');       // Equivale a 'addressPhone' en Boxful
            $table->string('codigo_area')->default('503'); // Equivale a 'addressAreaCode'
            
            $table->decimal('latitud', 10, 7);  // Equivale a 'latitude'
            $table->decimal('longitud', 11, 7); // Equivale a 'longitude'
            
            // Estos IDs los obtienes del endpoint GET /states de Boxful
            $table->string('boxful_state_id'); // Equivale a 'stateId'
            $table->string('boxful_city_id');  // Equivale a 'cityId'
            
            // 🚨 EL CAMPO CLAVE 🚨
            // Aquí guardas el ID que Boxful te devuelve cuando haces POST /addresses
            $table->string('boxful_address_id')->unique()->nullable(); 
            
            $table->boolean('es_predeterminada')->default(false);
            $table->timestamps();

            // Llaves foráneas
            $table->foreign('id_empresa')->references('id')->on('empresas')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('direcciones_origen');
    }
}
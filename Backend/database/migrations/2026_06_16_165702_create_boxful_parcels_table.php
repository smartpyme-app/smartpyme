<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('boxful_parcels', function (Blueprint $table) {
            $table->id();
            
            // Relación con el Envío Logístico (Varias cajas pertenecen a 1 envío)
            $table->unsignedBigInteger('boxful_shipment_id');

            // Dimensiones Físicas (Lo que Boxful pide en el array 'parcels')
            $table->text('contenido'); // content (Ej. "Ropa, documentos")
            $table->decimal('alto', 8, 2);   // height
            $table->decimal('ancho', 8, 2);  // width
            $table->decimal('largo', 8, 2);  // length
            $table->decimal('peso', 8, 2);   // weight
            $table->decimal('valor_declarado', 10, 2)->default(0); // price (Para seguros)
            $table->boolean('es_fragil')->default(false); // isFragile

            $table->timestamps();

            // Llave foránea con cascade: si se borra el envío, se borran sus cajas
            $table->foreign('boxful_shipment_id')->references('id')->on('boxful_shipments')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::table('boxful_parcels', function (Blueprint $table) {
            $table->dropForeign(['boxful_shipment_id']);
        });
        Schema::dropIfExists('boxful_parcels');
    }
};
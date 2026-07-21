<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('boxful_shipments', function (Blueprint $table) {
            $table->id();
            
            // Relación con tu tabla legacy (La Venta / Orden original)
            $table->integer('paquete_id');
            
            // Relación con la bodega de origen (Direcciones de la empresa)
            $table->unsignedBigInteger('direccion_origen_id')->nullable();

            // Configuración del envío
            $table->dateTime('fecha_recoleccion')->nullable(); // recolectionDate (ISO8601)
            $table->boolean('cod')->default(false);
            $table->decimal('cod_monto', 10, 2)->default(0);

            // 🚨 DATOS DE RESPUESTA Y TRACKING DE BOXFUL 🚨
            $table->string('boxful_shipment_id')->unique()->nullable(); // ID interno de Boxful
            $table->string('shipment_number')->nullable(); // Número de guía (Ej. 9405622492)
            
            $table->string('boxful_courier_id')->nullable();
            $table->string('boxful_courier_name')->nullable();
            
            $table->text('boxful_label_url')->nullable(); // PDF de la etiqueta
            $table->text('boxful_tracking_url')->nullable(); // Link para el cliente
            
            // Estados (Actualizados por el Webhook: -1 a 11)
            $table->tinyInteger('boxful_status')->nullable(); 
            $table->string('boxful_status_description')->nullable(); // "Entregado", "Recolectado"

            $table->timestamps();

            // Llaves foráneas
            $table->foreign('paquete_id')->references('id')->on('paquetes')->onDelete('cascade');
            $table->foreign('direccion_origen_id')->references('id')->on('direcciones_origen')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('boxful_shipments', function (Blueprint $table) {
            $table->dropForeign(['paquete_id']);
            $table->dropForeign(['direccion_origen_id']);
        });
        Schema::dropIfExists('boxful_shipments');
    }
};
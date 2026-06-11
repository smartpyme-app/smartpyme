<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBoxfulFieldsToPaquetesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('paquetes', function (Blueprint $table) {
            // 1. DIMENSIONES FÍSICAS (Obligatorias para Boxful parcels)
            $table->decimal('alto', 8, 2)->nullable()->after('peso');   // height
            $table->decimal('ancho', 8, 2)->nullable()->after('alto');  // width
            $table->decimal('largo', 8, 2)->nullable()->after('ancho'); // length
            $table->boolean('es_fragil')->default(false)->after('largo'); // isFragile
            
            // 2. DATOS DE RESPUESTA DE BOXFUL
            $table->string('boxful_shipment_id')->nullable()->after('num_guia');
            $table->string('boxful_courier_id')->nullable()->after('boxful_shipment_id');
            $table->string('boxful_courier_name')->nullable()->after('boxful_courier_id');
            $table->text('boxful_label_url')->nullable()->after('boxful_courier_name');
            $table->text('boxful_tracking_url')->nullable()->after('boxful_label_url');
            
            // 3. ESTADO DEL ENVÍO BOXFUL (según webhook: -1 a 11)
            $table->tinyInteger('boxful_status')->nullable()->after('boxful_tracking_url');
            
            // 4. RELACIÓN CON DIRECCIÓN DE ENVÍO (opcional, si usas direcciones guardadas)
            $table->foreignId('direccion_envio_id')->nullable()->after('boxful_status')
                  ->constrained('direcciones_envio')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('paquetes', function (Blueprint $table) {
            $table->dropForeign(['direccion_envio_id']);
            $table->dropColumn([
                'alto',
                'ancho',
                'largo',
                'es_fragil',
                'boxful_shipment_id',
                'boxful_courier_id',
                'boxful_courier_name',
                'boxful_label_url',
                'boxful_tracking_url',
                'boxful_status',
                'direccion_envio_id'
            ]);
        });
    }
}

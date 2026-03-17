<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDetalleEgresosTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('detalle_egresos')) {
            return;
        }
        Schema::create('detalle_egresos', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('id_egreso');
            $table->unsignedTinyInteger('numero_item')->default(1);
            $table->string('concepto');
            $table->string('tipo')->nullable(); // categoría por línea
            $table->unsignedInteger('id_categoria')->nullable();
            $table->decimal('cantidad', 12, 4)->default(1);
            $table->decimal('precio_unitario', 12, 4)->default(0);
            $table->decimal('sub_total', 12, 2)->default(0);
            $table->decimal('iva', 12, 2)->default(0);
            $table->decimal('renta_retenida', 12, 2)->default(0);
            $table->decimal('iva_percibido', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('area_empresa')->nullable();
            $table->unsignedInteger('id_proyecto')->nullable();
            $table->boolean('aplica_iva')->default(false);
            $table->boolean('aplica_renta')->default(false);
            $table->boolean('aplica_percepcion')->default(false);
            $table->timestamps();

            $table->foreign('id_egreso')->references('id')->on('egresos')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('detalle_egresos');
    }
}

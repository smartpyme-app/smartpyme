<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddExportacionFieldsToVentasTable extends Migration
{
    /**
     * Run the migrations.
     * Campos para completar información de Factura de Exportación: proforma, via, marcas, pago_descripcion.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->string('proforma', 255)->nullable();
            $table->string('via', 100)->nullable();
            $table->string('marcas', 255)->nullable();
            $table->string('pago_descripcion', 500)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn(['proforma', 'via', 'marcas', 'pago_descripcion']);
        });
    }
}

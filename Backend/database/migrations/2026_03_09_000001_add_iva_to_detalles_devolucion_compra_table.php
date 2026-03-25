<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIvaToDetallesDevolucionCompraTable extends Migration
{
    /**
     * Run the migrations.
     * Agrega el campo iva a detalles_devolucion_compra para alinearlo con el modelo y evitar
     * el error "detalles devoluciones no está el campo iva" al guardar una devolución de compra.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('detalles_devolucion_compra') && !Schema::hasColumn('detalles_devolucion_compra', 'iva')) {
            Schema::table('detalles_devolucion_compra', function (Blueprint $table) {
                $table->decimal('iva', 10, 2)->default(0);
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasColumn('detalles_devolucion_compra', 'iva')) {
            Schema::table('detalles_devolucion_compra', function (Blueprint $table) {
                $table->dropColumn('iva');
            });
        }
    }
}

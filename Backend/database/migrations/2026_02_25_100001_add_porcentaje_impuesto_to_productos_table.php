<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPorcentajeImpuestoToProductosTable extends Migration
{
    /**
     * Run the migrations.
     * Guarda el valor (porcentaje) del impuesto por producto, ej. 15 o 18.
     */
    public function up()
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->decimal('porcentaje_impuesto', 5, 2)->nullable()->after('id_categoria');
        });
    }

    public function down()
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn('porcentaje_impuesto');
        });
    }
}

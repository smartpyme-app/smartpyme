<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPorcentajeImpuestoToDetallesVentaTable extends Migration
{
    /**
     * Run the migrations.
     * Guarda la tasa de impuesto aplicada por línea (ej. 15 o 18) para facturación SAR Honduras.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('detalles_venta', function (Blueprint $table) {
            $table->decimal('porcentaje_impuesto', 5, 2)->nullable()->after('iva');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('detalles_venta', function (Blueprint $table) {
            $table->dropColumn('porcentaje_impuesto');
        });
    }
}

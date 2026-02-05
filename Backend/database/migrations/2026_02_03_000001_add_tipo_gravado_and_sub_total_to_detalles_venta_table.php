<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTipoGravadoAndSubTotalToDetallesVentaTable extends Migration
{
    /**
     * Run the migrations.
     * Agrega tipo_gravado (gravada|exenta|no_sujeta) y sub_total por detalle para facturación.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('detalles_venta', function (Blueprint $table) {
            $table->string('tipo_gravado', 20)->default('gravada')->after('iva')->comment('gravada, exenta, no_sujeta');
            $table->decimal('sub_total', 12, 4)->nullable()->after('descuento')->comment('cantidad * precio antes de descuento');
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
            $table->dropColumn(['tipo_gravado', 'sub_total']);
        });
    }
}

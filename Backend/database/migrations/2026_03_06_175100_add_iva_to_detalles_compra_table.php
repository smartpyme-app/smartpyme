<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIvaToDetallesCompraTable extends Migration
{
    /**
     * Run the migrations.
     * Monto de IVA por línea de detalle.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('detalles_compra', function (Blueprint $table) {
            $table->decimal('iva', 10, 2)->nullable()->after('porcentaje_impuesto');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('detalles_compra', function (Blueprint $table) {
            $table->dropColumn('iva');
        });
    }
}

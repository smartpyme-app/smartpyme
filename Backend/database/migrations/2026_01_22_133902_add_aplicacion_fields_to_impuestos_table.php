<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddAplicacionFieldsToImpuestosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('impuestos', function (Blueprint $table) {
            $table->boolean('aplica_ventas')->default(true)->after('porcentaje');
            $table->boolean('aplica_gastos')->default(true)->after('aplica_ventas');
            $table->boolean('aplica_compras')->default(true)->after('aplica_gastos');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('impuestos', function (Blueprint $table) {
            $table->dropColumn(['aplica_ventas', 'aplica_gastos', 'aplica_compras']);
        });
    }
}

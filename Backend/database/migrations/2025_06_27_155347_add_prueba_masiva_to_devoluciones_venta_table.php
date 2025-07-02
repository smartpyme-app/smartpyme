<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPruebaMasivaToDevolucionesVentaTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('devoluciones_venta', function (Blueprint $table) {
            $table->boolean('prueba_masiva')->default(false)->after('tipo_dte')
            ->comment('Indica si la nota fue generada como parte de pruebas masivas');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('devoluciones_venta', function (Blueprint $table) {
            $table->dropColumn('prueba_masiva');
        });
    }
}

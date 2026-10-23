<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMotivoFeCrToDevolucionesVentaTable extends Migration
{
    public function up()
    {
        Schema::table('devoluciones_venta', function (Blueprint $table) {
            $table->string('motivo_fe_cr', 40)->nullable();
        });
    }

    public function down()
    {
        Schema::table('devoluciones_venta', function (Blueprint $table) {
            $table->dropColumn('motivo_fe_cr');
        });
    }
}

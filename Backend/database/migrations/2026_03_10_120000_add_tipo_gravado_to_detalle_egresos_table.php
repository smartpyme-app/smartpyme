<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTipoGravadoToDetalleEgresosTable extends Migration
{
    public function up()
    {
        Schema::table('detalle_egresos', function (Blueprint $table) {
            $table->string('tipo_gravado', 20)->default('gravada')->after('tipo')->comment('gravada, exenta, no_sujeta');
        });
    }

    public function down()
    {
        Schema::table('detalle_egresos', function (Blueprint $table) {
            $table->dropColumn('tipo_gravado');
        });
    }
}

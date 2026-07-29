<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPeriodoToReporteConfiguracionesTable extends Migration
{
    public function up()
    {
        Schema::table('reporte_configuraciones', function (Blueprint $table) {
            $table->string('periodo', 32)->nullable()->after('frecuencia');
        });
    }

    public function down()
    {
        Schema::table('reporte_configuraciones', function (Blueprint $table) {
            $table->dropColumn('periodo');
        });
    }
}

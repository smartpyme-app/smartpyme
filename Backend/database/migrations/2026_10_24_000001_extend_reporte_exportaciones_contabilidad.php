<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ExtendReporteExportacionesContabilidad extends Migration
{
    public function up()
    {
        Schema::table('reporte_exportaciones', function (Blueprint $table) {
            $table->unsignedBigInteger('id_configuracion')->nullable()->change();
            $table->string('tipo_reporte', 64)->nullable()->after('id_configuracion');
            $table->json('parametros')->nullable()->after('tipo_reporte');
        });
    }

    public function down()
    {
        Schema::table('reporte_exportaciones', function (Blueprint $table) {
            $table->dropColumn(['tipo_reporte', 'parametros']);
            $table->unsignedBigInteger('id_configuracion')->nullable(false)->change();
        });
    }
}

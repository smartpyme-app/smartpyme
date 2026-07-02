<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddCostaRicaFieldsToDteModule extends Migration
{
    public function up(): void
    {
        Schema::table('dte_documents', function (Blueprint $table) {
            $table->char('pais', 2)->default('SV')->after('id_empresa');
            $table->string('formato_origen', 10)->default('json')->after('receiver_nit');
            $table->string('xml_path')->nullable()->after('json_path');
            $table->string('acuse_xml_path')->nullable()->after('xml_path');
            $table->string('acuse_estado')->nullable()->after('acuse_xml_path');
        });

        Schema::table('dte_tipo_mapeo', function (Blueprint $table) {
            $table->char('cod_pais', 2)->default('SV')->after('id');
        });

        Schema::table('dte_tipo_mapeo', function (Blueprint $table) {
            $table->dropUnique(['codigo_mh']);
            $table->unique(['cod_pais', 'codigo_mh'], 'dte_tipo_mapeo_pais_codigo_unique');
        });
    }

    public function down(): void
    {
        Schema::table('dte_tipo_mapeo', function (Blueprint $table) {
            $table->dropUnique('dte_tipo_mapeo_pais_codigo_unique');
            $table->unique('codigo_mh');
            $table->dropColumn('cod_pais');
        });

        Schema::table('dte_documents', function (Blueprint $table) {
            $table->dropColumn(['pais', 'formato_origen', 'xml_path', 'acuse_xml_path', 'acuse_estado']);
        });
    }
}

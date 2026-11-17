<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('numero_patronal_isss', 9)->nullable();
            $table->string('tipo_persona', 10)->nullable();
            $table->string('regimen_especial_isss', 20)->nullable();
        });

        Schema::table('sucursales', function (Blueprint $table) {
            $table->string('correlativo_centro_trabajo_isss', 3)->nullable();
        });

        Schema::table('empleados', function (Blueprint $table) {
            $table->string('institucion_previsional', 3)->nullable();
            $table->string('apellido_casada', 40)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn(['numero_patronal_isss', 'tipo_persona', 'regimen_especial_isss']);
        });

        Schema::table('sucursales', function (Blueprint $table) {
            $table->dropColumn('correlativo_centro_trabajo_isss');
        });

        Schema::table('empleados', function (Blueprint $table) {
            $table->dropColumn(['institucion_previsional', 'apellido_casada']);
        });
    }
};

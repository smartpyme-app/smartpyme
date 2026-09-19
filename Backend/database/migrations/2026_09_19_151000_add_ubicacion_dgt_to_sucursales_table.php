<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sucursales', function (Blueprint $table) {
            $table->string('distrito')->nullable()->after('departamento');
            $table->string('cod_departamento', 10)->nullable()->after('distrito');
            $table->string('cod_municipio', 10)->nullable()->after('cod_departamento');
            $table->string('cod_distrito', 10)->nullable()->after('cod_municipio');
        });
    }

    public function down(): void
    {
        Schema::table('sucursales', function (Blueprint $table) {
            $table->dropColumn(['distrito', 'cod_departamento', 'cod_municipio', 'cod_distrito']);
        });
    }
};

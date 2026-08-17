<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pre_cuentas_restaurante', function (Blueprint $table) {
            $table->string('nombre_pagador', 80)->nullable()->after('numero_pre_cuenta');
        });
    }

    public function down(): void
    {
        Schema::table('pre_cuentas_restaurante', function (Blueprint $table) {
            $table->dropColumn('nombre_pagador');
        });
    }
};

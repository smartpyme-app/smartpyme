<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prestamo_pagos', function (Blueprint $table) {
            $table->string('referencia')->nullable()->after('metodo');
            $table->string('detalle_banco')->nullable()->after('referencia');
        });
    }

    public function down(): void
    {
        Schema::table('prestamo_pagos', function (Blueprint $table) {
            $table->dropColumn(['referencia', 'detalle_banco']);
        });
    }
};

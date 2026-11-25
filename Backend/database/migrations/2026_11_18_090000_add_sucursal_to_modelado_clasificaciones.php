<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modelado_clasificaciones', function (Blueprint $table) {
            $table->string('sucursal', 191)->nullable()->after('proveedor');
        });
    }

    public function down(): void
    {
        Schema::table('modelado_clasificaciones', function (Blueprint $table) {
            $table->dropColumn('sucursal');
        });
    }
};

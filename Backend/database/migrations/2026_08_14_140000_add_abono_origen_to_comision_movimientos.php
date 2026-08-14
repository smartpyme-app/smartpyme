<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comision_movimientos', function (Blueprint $table) {
            $table->unsignedBigInteger('id_abono')->nullable()->after('id_detalle_venta');
        });
    }

    public function down(): void
    {
        Schema::table('comision_movimientos', function (Blueprint $table) {
            $table->dropColumn('id_abono');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->unsignedBigInteger('id_venta_programada')->nullable()->after('periodo_recurrencia');
            $table->foreign('id_venta_programada')
                ->references('id')
                ->on('ventas_programadas')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropForeign(['id_venta_programada']);
            $table->dropColumn('id_venta_programada');
        });
    }
};
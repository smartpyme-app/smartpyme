<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Detalle de horas extra por tipo (El Salvador): diurna, nocturna, dia_descanso, dia_asueto.
     */
    public function up(): void
    {
        Schema::table('planilla_detalles', function (Blueprint $table) {
            $table->json('detalle_horas_extra')->nullable()->after('monto_horas_extra');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('planilla_detalles', function (Blueprint $table) {
            $table->dropColumn('detalle_horas_extra');
        });
    }
};

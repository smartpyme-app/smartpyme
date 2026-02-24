<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Abonos: monto opcional. Si abonos_sin_retencion es true, no aplican ISSS, AFP ni ISR.
     */
    public function up(): void
    {
        Schema::table('planilla_detalles', function (Blueprint $table) {
            $table->decimal('abonos', 10, 2)->default(0)->after('otros_ingresos');
            $table->boolean('abonos_sin_retencion')->default(true)->after('abonos');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('planilla_detalles', function (Blueprint $table) {
            $table->dropColumn(['abonos', 'abonos_sin_retencion']);
        });
    }
};

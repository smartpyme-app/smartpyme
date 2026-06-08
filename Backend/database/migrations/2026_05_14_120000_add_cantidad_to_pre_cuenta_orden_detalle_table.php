<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cantidad de la línea de orden asignada a esta pre-cuenta (división por ítems).
     * Null = toda la línea (pre-cuenta única o compatibilidad).
     */
    public function up(): void
    {
        Schema::table('pre_cuenta_orden_detalle', function (Blueprint $table) {
            $table->decimal('cantidad', 12, 4)->nullable()->after('orden_detalle_id');
        });
    }

    public function down(): void
    {
        Schema::table('pre_cuenta_orden_detalle', function (Blueprint $table) {
            $table->dropColumn('cantidad');
        });
    }
};

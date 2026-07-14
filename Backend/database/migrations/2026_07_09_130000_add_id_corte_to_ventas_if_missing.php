<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El esquema actual de ventas usa id_corte; relaciones legacy usaban corte_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ventas')) {
            return;
        }

        if (! Schema::hasColumn('ventas', 'id_corte')) {
            Schema::table('ventas', function (Blueprint $table) {
                $table->unsignedInteger('id_corte')->nullable();
            });
        }

        if (
            Schema::hasColumn('ventas', 'id_corte')
            && Schema::hasColumn('ventas', 'corte_id')
        ) {
            DB::table('ventas')
                ->whereNull('id_corte')
                ->whereNotNull('corte_id')
                ->update(['id_corte' => DB::raw('corte_id')]);
        }
    }

    public function down(): void
    {
        // Sin reversión: evita pérdida de vínculo venta–corte en producción.
    }
};

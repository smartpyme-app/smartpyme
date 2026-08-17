<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comision_movimientos', function (Blueprint $table) {
            $table->dropUnique('comision_mov_unique_redencion');
        });

        Schema::table('comision_movimientos', function (Blueprint $table) {
            $table->unique(
                ['id_empresa', 'id_gift_card_redencion', 'id_regla'],
                'comision_mov_unique_redencion_regla'
            );
        });
    }

    public function down(): void
    {
        throw new RuntimeException(
            'Esta migración es irreversible: una redención puede tener movimientos para múltiples reglas.'
        );
    }
};

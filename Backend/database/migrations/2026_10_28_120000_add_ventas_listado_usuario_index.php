<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->index(
                ['id_empresa', 'cotizacion', 'id_usuario', 'fecha'],
                'idx_ventas_empresa_cotiz_usuario_fecha'
            );
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropIndex('idx_ventas_empresa_cotiz_usuario_fecha');
        });
    }
};

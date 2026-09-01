<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurante_pedido_detalles', function (Blueprint $table) {
            if (! Schema::hasColumn('restaurante_pedido_detalles', 'descuento_porcentaje')) {
                $table->decimal('descuento_porcentaje', 14, 4)->default(0)->after('descuento');
            }
        });
    }

    public function down(): void
    {
        Schema::table('restaurante_pedido_detalles', function (Blueprint $table) {
            if (Schema::hasColumn('restaurante_pedido_detalles', 'descuento_porcentaje')) {
                $table->dropColumn('descuento_porcentaje');
            }
        });
    }
};

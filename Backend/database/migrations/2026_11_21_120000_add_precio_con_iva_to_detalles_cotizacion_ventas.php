<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('detalles_cotizacion_ventas')) {
            return;
        }

        Schema::table('detalles_cotizacion_ventas', function (Blueprint $table) {
            if (! Schema::hasColumn('detalles_cotizacion_ventas', 'precio_sin_iva')) {
                $table->decimal('precio_sin_iva', 16, 6)->nullable()->after('precio');
            }
            if (! Schema::hasColumn('detalles_cotizacion_ventas', 'precio_con_iva')) {
                $table->decimal('precio_con_iva', 16, 6)->nullable()->after('precio_sin_iva');
            }
            if (! Schema::hasColumn('detalles_cotizacion_ventas', 'tipo_gravado')) {
                $table->string('tipo_gravado', 20)->default('gravada')->after('iva');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('detalles_cotizacion_ventas')) {
            return;
        }

        Schema::table('detalles_cotizacion_ventas', function (Blueprint $table) {
            foreach (['tipo_gravado', 'precio_con_iva', 'precio_sin_iva'] as $column) {
                if (Schema::hasColumn('detalles_cotizacion_ventas', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotizacion_ventas', function (Blueprint $table) {
            if (!Schema::hasColumn('cotizacion_ventas', 'referencia_shopify')) {
                $table->string('referencia_shopify', 191)->nullable()->after('id_sucursal');
            }
            if (!Schema::hasColumn('cotizacion_ventas', 'num_orden')) {
                $table->string('num_orden')->nullable()->after('referencia_shopify');
            }
        });

        Schema::table('cotizacion_ventas', function (Blueprint $table) {
            $table->index(['referencia_shopify', 'id_empresa'], 'cotizacion_ventas_referencia_shopify_empresa');
        });
    }

    public function down(): void
    {
        Schema::table('cotizacion_ventas', function (Blueprint $table) {
            $table->dropIndex('cotizacion_ventas_referencia_shopify_empresa');
            if (Schema::hasColumn('cotizacion_ventas', 'num_orden')) {
                $table->dropColumn('num_orden');
            }
            if (Schema::hasColumn('cotizacion_ventas', 'referencia_shopify')) {
                $table->dropColumn('referencia_shopify');
            }
        });
    }
};

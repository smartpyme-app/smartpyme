<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contabilidad_configuracion')) {
            return;
        }

        Schema::table('contabilidad_configuracion', function (Blueprint $table) {
            if (! Schema::hasColumn('contabilidad_configuracion', 'separar_cuentas_iva')) {
                $table->boolean('separar_cuentas_iva')->default(false);
            }
            if (! Schema::hasColumn('contabilidad_configuracion', 'abonos_en_cartera')) {
                $table->boolean('abonos_en_cartera')->default(false);
            }
            if (! Schema::hasColumn('contabilidad_configuracion', 'id_cuenta_iva_ventas_cf')) {
                $table->unsignedBigInteger('id_cuenta_iva_ventas_cf')->nullable();
            }
            if (! Schema::hasColumn('contabilidad_configuracion', 'id_cuenta_iva_compras_cf')) {
                $table->unsignedBigInteger('id_cuenta_iva_compras_cf')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('contabilidad_configuracion')) {
            return;
        }

        Schema::table('contabilidad_configuracion', function (Blueprint $table) {
            foreach (['separar_cuentas_iva', 'abonos_en_cartera', 'id_cuenta_iva_ventas_cf', 'id_cuenta_iva_compras_cf'] as $col) {
                if (Schema::hasColumn('contabilidad_configuracion', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

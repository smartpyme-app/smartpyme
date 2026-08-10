<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contabilidad_configuracion')) {
            Schema::table('contabilidad_configuracion', function (Blueprint $table) {
                if (!Schema::hasColumn('contabilidad_configuracion', 'id_cuenta_gasto_salarios')) {
                    $table->unsignedBigInteger('id_cuenta_gasto_salarios')->nullable();
                }
                if (!Schema::hasColumn('contabilidad_configuracion', 'id_cuenta_gasto_cargas_patronales')) {
                    $table->unsignedBigInteger('id_cuenta_gasto_cargas_patronales')->nullable();
                }
                if (!Schema::hasColumn('contabilidad_configuracion', 'id_cuenta_gasto_aguinaldo')) {
                    $table->unsignedBigInteger('id_cuenta_gasto_aguinaldo')->nullable();
                }
                if (!Schema::hasColumn('contabilidad_configuracion', 'id_cuenta_gasto_vacaciones')) {
                    $table->unsignedBigInteger('id_cuenta_gasto_vacaciones')->nullable();
                }
                if (!Schema::hasColumn('contabilidad_configuracion', 'id_cuenta_pasivo_cargas_sociales')) {
                    $table->unsignedBigInteger('id_cuenta_pasivo_cargas_sociales')->nullable();
                }
                if (!Schema::hasColumn('contabilidad_configuracion', 'id_cuenta_pasivo_ins')) {
                    $table->unsignedBigInteger('id_cuenta_pasivo_ins')->nullable();
                }
                if (!Schema::hasColumn('contabilidad_configuracion', 'id_cuenta_pasivo_retencion_renta')) {
                    $table->unsignedBigInteger('id_cuenta_pasivo_retencion_renta')->nullable();
                }
                if (!Schema::hasColumn('contabilidad_configuracion', 'id_cuenta_pasivo_salarios_por_pagar')) {
                    $table->unsignedBigInteger('id_cuenta_pasivo_salarios_por_pagar')->nullable();
                }
                if (!Schema::hasColumn('contabilidad_configuracion', 'id_cuenta_pasivo_provision_aguinaldo')) {
                    $table->unsignedBigInteger('id_cuenta_pasivo_provision_aguinaldo')->nullable();
                }
                if (!Schema::hasColumn('contabilidad_configuracion', 'id_cuenta_pasivo_provision_vacaciones')) {
                    $table->unsignedBigInteger('id_cuenta_pasivo_provision_vacaciones')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('contabilidad_configuracion')) {
            Schema::table('contabilidad_configuracion', function (Blueprint $table) {
                $columns = [
                    'id_cuenta_gasto_salarios',
                    'id_cuenta_gasto_cargas_patronales',
                    'id_cuenta_gasto_aguinaldo',
                    'id_cuenta_gasto_vacaciones',
                    'id_cuenta_pasivo_cargas_sociales',
                    'id_cuenta_pasivo_ins',
                    'id_cuenta_pasivo_retencion_renta',
                    'id_cuenta_pasivo_salarios_por_pagar',
                    'id_cuenta_pasivo_provision_aguinaldo',
                    'id_cuenta_pasivo_provision_vacaciones',
                ];
                foreach ($columns as $col) {
                    if (Schema::hasColumn('contabilidad_configuracion', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};

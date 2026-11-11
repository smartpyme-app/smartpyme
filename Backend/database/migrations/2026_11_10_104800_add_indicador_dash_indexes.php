<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venta_metodos_pago', function (Blueprint $table) {
            $table->index(['id_venta'], 'idx_venta_metodos_pago_id_venta');
        });

        Schema::table('compras', function (Blueprint $table) {
            $table->index(['id_empresa', 'id_sucursal', 'fecha', 'estado'], 'idx_compras_empresa_sucursal_fecha');
            $table->index(['id_empresa', 'fecha', 'estado'], 'idx_compras_empresa_fecha');
            $table->index(['id_empresa', 'id_sucursal', 'created_at'], 'idx_compras_empresa_sucursal_created');
        });

        Schema::table('egresos', function (Blueprint $table) {
            $table->index(['id_empresa', 'id_sucursal', 'fecha', 'estado'], 'idx_egresos_empresa_sucursal_fecha');
            $table->index(['id_empresa', 'fecha', 'estado'], 'idx_egresos_empresa_fecha');
            $table->index(['id_empresa', 'id_sucursal', 'created_at'], 'idx_egresos_empresa_sucursal_created');
        });

        Schema::table('devoluciones_venta', function (Blueprint $table) {
            $table->index(['id_empresa', 'enable', 'fecha'], 'idx_devoluciones_venta_emp_enable_fecha');
        });

        Schema::table('devoluciones_compra', function (Blueprint $table) {
            $table->index(['id_compra'], 'idx_devoluciones_compra_id_compra');
            $table->index(['id_empresa', 'enable', 'fecha'], 'idx_devoluciones_compra_emp_enable_fecha');
        });

        Schema::table('ventas', function (Blueprint $table) {
            $table->index(['id_empresa', 'id_sucursal', 'created_at'], 'idx_ventas_empresa_sucursal_created');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropIndex('idx_ventas_empresa_sucursal_created');
        });

        Schema::table('devoluciones_compra', function (Blueprint $table) {
            $table->dropIndex('idx_devoluciones_compra_id_compra');
            $table->dropIndex('idx_devoluciones_compra_emp_enable_fecha');
        });

        Schema::table('devoluciones_venta', function (Blueprint $table) {
            $table->dropIndex('idx_devoluciones_venta_emp_enable_fecha');
        });

        Schema::table('egresos', function (Blueprint $table) {
            $table->dropIndex('idx_egresos_empresa_sucursal_fecha');
            $table->dropIndex('idx_egresos_empresa_fecha');
            $table->dropIndex('idx_egresos_empresa_sucursal_created');
        });

        Schema::table('compras', function (Blueprint $table) {
            $table->dropIndex('idx_compras_empresa_sucursal_fecha');
            $table->dropIndex('idx_compras_empresa_fecha');
            $table->dropIndex('idx_compras_empresa_sucursal_created');
        });

        Schema::table('venta_metodos_pago', function (Blueprint $table) {
            $table->dropIndex('idx_venta_metodos_pago_id_venta');
        });
    }
};

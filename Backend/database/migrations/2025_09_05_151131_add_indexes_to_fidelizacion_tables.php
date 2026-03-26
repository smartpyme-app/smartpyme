<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexesToFidelizacionTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('fidelizacion_tables', function (Blueprint $table) {
            // Índices para tipos_cliente_base
        Schema::table('tipos_cliente_base', function (Blueprint $table) {
            $table->index('activo');
            $table->index('orden');
        });

        // Índices para tipos_cliente_empresa
        Schema::table('tipos_cliente_empresa', function (Blueprint $table) {
            $table->index(['id_empresa', 'activo']);
            $table->index('is_default');
        });

        Schema::table('tipos_cliente_empresa', function (Blueprint $table) {
            $table->index('nivel');
        });

        // Índices para clientes
        Schema::table('clientes', function (Blueprint $table) {
            $table->index(['id_empresa', 'enable']);
            $table->index('correo');
            $table->index('telefono');
        });

        // Índices para puntos_cliente
        Schema::table('puntos_cliente', function (Blueprint $table) {
            $table->index(['id_empresa', 'puntos_disponibles']);
            $table->index('fecha_ultima_actividad');
        });

        // Índices para transacciones_puntos
        Schema::table('transacciones_puntos', function (Blueprint $table) {
            $table->index(['id_cliente', 'created_at']);
            $table->index(['id_empresa', 'tipo', 'created_at']);
            $table->index(['fecha_expiracion', 'tipo']);
            $table->index(['id_venta', 'tipo']);
        });

        // Índices para consumo_puntos
        Schema::table('consumo_puntos', function (Blueprint $table) {
            $table->index(['id_cliente', 'created_at']);
            $table->index('id_canje_tx');
            $table->index('id_ganancia_tx');
        });

        // Índices para ventas
        Schema::table('ventas', function (Blueprint $table) {
            $table->index(['id_cliente', 'created_at']);
            $table->index(['id_empresa', 'created_at']);
            $table->index('puntos_ganados');
        });

        
        });

        Schema::table('clientes', function (Blueprint $table) {
            $table->index(['id_empresa', 'nivel']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('fidelizacion_tables', function (Blueprint $table) {
            Schema::table('ventas', function (Blueprint $table) {
                $table->dropIndex(['id_cliente', 'created_at']);
                $table->dropIndex(['id_empresa', 'created_at']);
                $table->dropIndex(['puntos_ganados']);
            });
    
            Schema::table('consumo_puntos', function (Blueprint $table) {
                $table->dropIndex(['id_cliente', 'created_at']);
                $table->dropIndex(['id_canje_tx']);
                $table->dropIndex(['id_ganancia_tx']);
            });
    
            Schema::table('transacciones_puntos', function (Blueprint $table) {
                $table->dropIndex(['id_cliente', 'created_at']);
                $table->dropIndex(['id_empresa', 'tipo', 'created_at']);
                $table->dropIndex(['fecha_expiracion', 'tipo']);
                $table->dropIndex(['venta_id', 'tipo']);
            });
    
            Schema::table('puntos_cliente', function (Blueprint $table) {
                $table->dropIndex(['id_empresa', 'puntos_disponibles']);
                $table->dropIndex(['fecha_ultima_actividad']);
            });
    
            Schema::table('clientes', function (Blueprint $table) {
                $table->dropIndex(['id_empresa', 'enable']);
                $table->dropIndex(['id_empresa', 'nivel']);
                $table->dropIndex(['correo']);
                $table->dropIndex(['telefono']);
            });
    
            Schema::table('tipos_cliente_empresa', function (Blueprint $table) {
                $table->dropIndex(['id_empresa', 'activo']);
                $table->dropIndex(['is_default']);
            });
    
            Schema::table('tipos_cliente_base', function (Blueprint $table) {
                $table->dropIndex(['activo']);
                $table->dropIndex(['orden']);
            });

            Schema::table('clientes', function (Blueprint $table) {
                $table->dropIndex(['id_empresa', 'nivel']);
            });

            Schema::table('tipos_cliente_empresa', function (Blueprint $table) {
                $table->dropIndex(['nivel']);
            });
        });
    }
}

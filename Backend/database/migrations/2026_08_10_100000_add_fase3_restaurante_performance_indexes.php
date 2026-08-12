<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Índices Fase 3 justificados por EXPLAIN / patrones de consulta.
 * - orden_detalle: fusión de ítems por sesión+producto+flags enviado
 * - sesiones: lookup sesión activa por mesa+estado (histórico crece)
 * comandas (id_empresa, estado, created_at) ya existe desde Fase 2.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orden_detalle_restaurante')) {
            $indexes = collect(DB::select('SHOW INDEX FROM orden_detalle_restaurante'))->pluck('Key_name');
            if (! $indexes->contains('orden_detalle_rest_sesion_prod_enviado_index')) {
                Schema::table('orden_detalle_restaurante', function (Blueprint $table) {
                    $table->index(
                        ['sesion_id', 'producto_id', 'enviado_cocina', 'enviado_barra'],
                        'orden_detalle_rest_sesion_prod_enviado_index'
                    );
                });
            }
        }

        if (Schema::hasTable('restaurante_sesiones_mesa')) {
            $indexes = collect(DB::select('SHOW INDEX FROM restaurante_sesiones_mesa'))->pluck('Key_name');
            if (! $indexes->contains('restaurante_sesiones_mesa_mesa_id_estado_index')) {
                Schema::table('restaurante_sesiones_mesa', function (Blueprint $table) {
                    $table->index(
                        ['mesa_id', 'estado'],
                        'restaurante_sesiones_mesa_mesa_id_estado_index'
                    );
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('orden_detalle_restaurante')) {
            $indexes = collect(DB::select('SHOW INDEX FROM orden_detalle_restaurante'))->pluck('Key_name');
            if ($indexes->contains('orden_detalle_rest_sesion_prod_enviado_index')) {
                Schema::table('orden_detalle_restaurante', function (Blueprint $table) {
                    $table->dropIndex('orden_detalle_rest_sesion_prod_enviado_index');
                });
            }
        }

        if (Schema::hasTable('restaurante_sesiones_mesa')) {
            $indexes = collect(DB::select('SHOW INDEX FROM restaurante_sesiones_mesa'))->pluck('Key_name');
            if ($indexes->contains('restaurante_sesiones_mesa_mesa_id_estado_index')) {
                Schema::table('restaurante_sesiones_mesa', function (Blueprint $table) {
                    $table->dropIndex('restaurante_sesiones_mesa_mesa_id_estado_index');
                });
            }
        }
    }
};

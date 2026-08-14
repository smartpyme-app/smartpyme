<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Denormaliza id_empresa en comandas para evitar whereHas en cocina.
 * Backfill desde sesión o pedido; índice (id_empresa, estado, created_at).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('comandas_restaurante')) {
            return;
        }

        if (! Schema::hasColumn('comandas_restaurante', 'id_empresa')) {
            Schema::table('comandas_restaurante', function (Blueprint $table) {
                $table->unsignedInteger('id_empresa')->nullable()->after('id');
            });
        }

        DB::statement('
            UPDATE comandas_restaurante c
            LEFT JOIN restaurante_sesiones_mesa s ON s.id = c.sesion_id
            LEFT JOIN restaurante_pedidos p ON p.id = c.pedido_id
            SET c.id_empresa = COALESCE(s.id_empresa, p.id_empresa)
            WHERE c.id_empresa IS NULL
        ');

        $orphan = (int) DB::table('comandas_restaurante')->whereNull('id_empresa')->count();
        if ($orphan === 0) {
            // MariaDB/MySQL: modificar a NOT NULL si no hay huérfanos
            DB::statement('ALTER TABLE comandas_restaurante MODIFY id_empresa INT UNSIGNED NOT NULL');
        }

        $indexes = collect(DB::select('SHOW INDEX FROM comandas_restaurante'))
            ->pluck('Key_name');
        if (! $indexes->contains('comandas_restaurante_id_empresa_estado_created_at_index')) {
            Schema::table('comandas_restaurante', function (Blueprint $table) {
                $table->index(
                    ['id_empresa', 'estado', 'created_at'],
                    'comandas_restaurante_id_empresa_estado_created_at_index'
                );
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('comandas_restaurante')) {
            return;
        }

        $indexes = collect(DB::select('SHOW INDEX FROM comandas_restaurante'))
            ->pluck('Key_name');
        if ($indexes->contains('comandas_restaurante_id_empresa_estado_created_at_index')) {
            Schema::table('comandas_restaurante', function (Blueprint $table) {
                $table->dropIndex('comandas_restaurante_id_empresa_estado_created_at_index');
            });
        }

        if (Schema::hasColumn('comandas_restaurante', 'id_empresa')) {
            Schema::table('comandas_restaurante', function (Blueprint $table) {
                $table->dropColumn('id_empresa');
            });
        }
    }
};

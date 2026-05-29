<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Añade id_empresa para snapshot por cliente-empresa (puntos_cliente es por empresa).
     */
    public function up(): void
    {
        Schema::table('cliente_fidelizacion_snapshot', function (Blueprint $table) {
            if (!Schema::hasColumn('cliente_fidelizacion_snapshot', 'id_empresa')) {
                $table->unsignedInteger('id_empresa')->nullable()->after('id_cliente');
            }
        });

        Schema::table('cliente_fidelizacion_snapshot', function (Blueprint $table) {
            if (Schema::hasColumn('cliente_fidelizacion_snapshot', 'id_empresa')) {
                $table->index(['id_cliente', 'id_empresa']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cliente_fidelizacion_snapshot', function (Blueprint $table) {
            if (Schema::hasColumn('cliente_fidelizacion_snapshot', 'id_empresa')) {
                $table->dropIndex(['id_cliente', 'id_empresa']);
            }
        });
        Schema::table('cliente_fidelizacion_snapshot', function (Blueprint $table) {
            if (Schema::hasColumn('cliente_fidelizacion_snapshot', 'id_empresa')) {
                $table->dropColumn('id_empresa');
            }
        });
    }
};

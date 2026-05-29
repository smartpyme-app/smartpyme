<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexesToVentasTableForExportOptimization extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->index(['id_empresa', 'cotizacion', 'fecha'], 'idx_ventas_export_base');
            $table->index('id_vendedor', 'idx_ventas_id_vendedor');
            $table->index('id_usuario', 'idx_ventas_id_usuario');
            $table->index('id_documento', 'idx_ventas_id_documento');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropIndex('idx_ventas_export_base');
            $table->dropIndex('idx_ventas_id_vendedor');
            $table->dropIndex('idx_ventas_id_usuario');
            $table->dropIndex('idx_ventas_id_documento');
        });
    }
}

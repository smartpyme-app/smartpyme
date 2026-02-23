<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('ventas', function (Blueprint $table) {
            // Verificar si la columna referencia existe, si no, crearla
            if (!Schema::hasColumn('ventas', 'referencia_shopify')) {
                $table->string('referencia_shopify')->nullable()->after('fecha_pago');
            }
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
            // Solo eliminar la columna si existe
            if (Schema::hasColumn('ventas', 'referencia_shopify')) {
                $table->dropColumn('referencia_shopify');
            }
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRestringirComprasSupervisorLimitadoToEmpresasTable extends Migration
{
    /**
     * Preferencia: cuando es true, usuarios "Supervisor Limitado" tienen acceso
     * restringido a compras y órdenes de compra (solo ver detalles, sin montos, etc.).
     * Por defecto false para no cambiar el comportamiento actual.
     */
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->boolean('restringir_compras_supervisor_limitado')
                ->default(false)
                ->after('modulo_proyectos');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn('restringir_compras_supervisor_limitado');
        });
    }
}

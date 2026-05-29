<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRestringirGastosSupervisorLimitadoToEmpresasTable extends Migration
{
    /**
     * Preferencia: cuando es true, usuarios "Supervisor Limitado" no pueden gestionar gastos.
     * Por defecto false para no cambiar el comportamiento actual.
     */
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->boolean('restringir_gastos_supervisor_limitado')
                ->default(false)
                ->after('modulo_proyectos');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn('restringir_gastos_supervisor_limitado');
        });
    }
}

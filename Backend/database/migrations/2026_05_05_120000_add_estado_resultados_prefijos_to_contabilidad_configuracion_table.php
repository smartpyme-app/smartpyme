<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contabilidad_configuracion')) {
            return;
        }

        Schema::table('contabilidad_configuracion', function (Blueprint $table) {
            if (! Schema::hasColumn('contabilidad_configuracion', 'estado_resultados_prefijos')) {
                $table->json('estado_resultados_prefijos')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('contabilidad_configuracion')) {
            return;
        }

        Schema::table('contabilidad_configuracion', function (Blueprint $table) {
            if (Schema::hasColumn('contabilidad_configuracion', 'estado_resultados_prefijos')) {
                $table->dropColumn('estado_resultados_prefijos');
            }
        });
    }
};

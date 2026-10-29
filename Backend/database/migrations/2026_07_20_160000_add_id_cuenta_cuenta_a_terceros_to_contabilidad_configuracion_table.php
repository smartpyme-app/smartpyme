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
            if (! Schema::hasColumn('contabilidad_configuracion', 'id_cuenta_cuenta_a_terceros')) {
                $table->unsignedBigInteger('id_cuenta_cuenta_a_terceros')->nullable()->after('id_cuenta_propina_ventas');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('contabilidad_configuracion')) {
            return;
        }

        Schema::table('contabilidad_configuracion', function (Blueprint $table) {
            if (Schema::hasColumn('contabilidad_configuracion', 'id_cuenta_cuenta_a_terceros')) {
                $table->dropColumn('id_cuenta_cuenta_a_terceros');
            }
        });
    }
};

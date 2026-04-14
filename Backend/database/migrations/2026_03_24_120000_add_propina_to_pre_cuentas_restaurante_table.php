<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pre_cuentas_restaurante', function (Blueprint $table) {
            $table->decimal('propina_monto', 12, 2)->default(0)->after('impuesto');
            $table->decimal('propina_porcentaje_aplicado', 6, 2)->nullable()->after('propina_monto');
        });
    }

    public function down(): void
    {
        Schema::table('pre_cuentas_restaurante', function (Blueprint $table) {
            $table->dropColumn(['propina_monto', 'propina_porcentaje_aplicado']);
        });
    }
};

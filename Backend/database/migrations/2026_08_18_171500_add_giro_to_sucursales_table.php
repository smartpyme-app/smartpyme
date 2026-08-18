<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sucursales', function (Blueprint $table) {
            $table->string('cod_actividad_economica', 15)->nullable()->after('codigo_punto_venta');
            $table->text('giro')->nullable()->after('cod_actividad_economica');
        });
    }

    public function down(): void
    {
        Schema::table('sucursales', function (Blueprint $table) {
            $table->dropColumn(['cod_actividad_economica', 'giro']);
        });
    }
};

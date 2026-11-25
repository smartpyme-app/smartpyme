<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modelado_clasificaciones', function (Blueprint $table) {
            $table->decimal('pct_ventas', 8, 4)->nullable()->after('monto');
            $table->string('dia_pago', 32)->nullable()->after('pct_ventas');
        });
    }

    public function down(): void
    {
        Schema::table('modelado_clasificaciones', function (Blueprint $table) {
            $table->dropColumn(['pct_ventas', 'dia_pago']);
        });
    }
};

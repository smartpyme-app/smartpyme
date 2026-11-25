<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            if (! Schema::hasColumn('ventas', 'condicion_venta_fe')) {
                $table->string('condicion_venta_fe', 2)->nullable()->after('condicion');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            if (Schema::hasColumn('ventas', 'condicion_venta_fe')) {
                $table->dropColumn('condicion_venta_fe');
            }
        });
    }
};

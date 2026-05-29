<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detalles_venta', function (Blueprint $table) {
            if (! Schema::hasColumn('detalles_venta', 'fe_cr_exoneracion')) {
                $table->json('fe_cr_exoneracion')->nullable()->after('porcentaje_impuesto');
            }
        });
    }

    public function down(): void
    {
        Schema::table('detalles_venta', function (Blueprint $table) {
            if (Schema::hasColumn('detalles_venta', 'fe_cr_exoneracion')) {
                $table->dropColumn('fe_cr_exoneracion');
            }
        });
    }
};

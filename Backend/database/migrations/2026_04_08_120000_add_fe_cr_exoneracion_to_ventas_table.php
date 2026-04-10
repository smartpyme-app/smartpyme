<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            if (! Schema::hasColumn('ventas', 'fe_cr_exoneracion')) {
                $table->json('fe_cr_exoneracion')->nullable()->after('dte');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            if (Schema::hasColumn('ventas', 'fe_cr_exoneracion')) {
                $table->dropColumn('fe_cr_exoneracion');
            }
        });
    }
};

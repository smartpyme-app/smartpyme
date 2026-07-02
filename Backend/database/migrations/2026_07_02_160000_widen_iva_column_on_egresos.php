<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WidenIvaColumnOnEgresos extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('egresos') || ! Schema::hasColumn('egresos', 'iva')) {
            return;
        }

        DB::table('egresos')->whereNull('iva')->update(['iva' => 0]);

        Schema::table('egresos', function (Blueprint $table) {
            $table->decimal('iva', 12, 2)->nullable()->default(0)->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('egresos') || ! Schema::hasColumn('egresos', 'iva')) {
            return;
        }

        Schema::table('egresos', function (Blueprint $table) {
            $table->decimal('iva', 6, 2)->nullable()->default(0)->change();
        });
    }
}

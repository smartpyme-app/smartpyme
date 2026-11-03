<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('planillas')) {
            return;
        }

        Schema::table('planillas', function (Blueprint $table) {
            if (!Schema::hasColumn('planillas', 'id_cuenta_banco')) {
                $table->unsignedInteger('id_cuenta_banco')->nullable()->after('id_sucursal');
            }
            if (!Schema::hasColumn('planillas', 'id_cuenta_contable')) {
                $table->unsignedInteger('id_cuenta_contable')->nullable()->after('id_cuenta_banco');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('planillas')) {
            return;
        }

        Schema::table('planillas', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('planillas', 'id_cuenta_contable')) {
                $cols[] = 'id_cuenta_contable';
            }
            if (Schema::hasColumn('planillas', 'id_cuenta_banco')) {
                $cols[] = 'id_cuenta_banco';
            }
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
};

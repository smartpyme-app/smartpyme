<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('planillas')) {
            Schema::table('planillas', function (Blueprint $table) {
                if (!Schema::hasColumn('planillas', 'version_tabla')) {
                    $table->string('version_tabla', 100)->nullable()->after('tipo_planilla');
                }
                if (!Schema::hasColumn('planillas', 'version_decreto')) {
                    $table->string('version_decreto', 100)->nullable()->after('version_tabla');
                }
                if (!Schema::hasColumn('planillas', 'fecha_vigencia_tabla')) {
                    $table->date('fecha_vigencia_tabla')->nullable()->after('version_decreto');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('planillas')) {
            Schema::table('planillas', function (Blueprint $table) {
                $columns = ['version_tabla', 'version_decreto', 'fecha_vigencia_tabla'];
                foreach ($columns as $col) {
                    if (Schema::hasColumn('planillas', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bono_reglas', function (Blueprint $table) {
            $table->boolean('reemplaza_global')->default(false)->after('alcance');
        });
        Schema::table('bono_generados', function (Blueprint $table) {
            $table->string('origen', 32)->default('evaluacion')->after('estado');
        });
    }

    public function down(): void
    {
        Schema::table('bono_reglas', function (Blueprint $table) {
            $table->dropColumn('reemplaza_global');
        });
        Schema::table('bono_generados', function (Blueprint $table) {
            $table->dropColumn('origen');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('productos', 'partida_arancelaria')) {
            return;
        }

        Schema::table('productos', function (Blueprint $table) {
            $table->string('partida_arancelaria', 12)->nullable()->after('codigo_cabys');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('productos', 'partida_arancelaria')) {
            return;
        }

        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn('partida_arancelaria');
        });
    }
};

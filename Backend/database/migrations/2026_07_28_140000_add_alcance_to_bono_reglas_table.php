<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bono_reglas', function (Blueprint $table) {
            $table->string('alcance', 32)->default('global')->after('activo');
            $table->json('id_vendedores')->nullable()->after('alcance');
        });
    }

    public function down(): void
    {
        Schema::table('bono_reglas', function (Blueprint $table) {
            $table->dropColumn(['alcance', 'id_vendedores']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->string('codigo_cabys', 13)->nullable()->after('codigo');
            $table->string('descripcion_cabys', 512)->nullable()->after('codigo_cabys');
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn(['codigo_cabys', 'descripcion_cabys']);
        });
    }
};

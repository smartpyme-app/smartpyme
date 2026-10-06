<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contador_empresa_documentos', function (Blueprint $table) {
            $table->string('titulo', 255)->nullable()->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('contador_empresa_documentos', function (Blueprint $table) {
            $table->dropColumn('titulo');
        });
    }
};

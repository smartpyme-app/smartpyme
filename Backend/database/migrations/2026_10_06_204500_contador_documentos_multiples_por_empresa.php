<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contador_empresa_documentos', function (Blueprint $table) {
            $table->index('id_empresa', 'contador_empresa_documentos_id_empresa_idx');
        });

        Schema::table('contador_empresa_documentos', function (Blueprint $table) {
            $table->dropUnique('contador_empresa_documentos_empresa_slug');
        });
    }

    public function down(): void
    {
        Schema::table('contador_empresa_documentos', function (Blueprint $table) {
            $table->unique(['id_empresa', 'slug'], 'contador_empresa_documentos_empresa_slug');
        });

        Schema::table('contador_empresa_documentos', function (Blueprint $table) {
            $table->dropIndex('contador_empresa_documentos_id_empresa_idx');
        });
    }
};

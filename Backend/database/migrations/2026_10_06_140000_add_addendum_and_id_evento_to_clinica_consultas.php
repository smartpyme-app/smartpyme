<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinica_consultas', function (Blueprint $table) {
            $table->unsignedBigInteger('id_evento')->nullable()->after('id_usuario_registro');
            $table->text('addendum')->nullable()->after('motivo_anulacion');
        });
    }

    public function down(): void
    {
        Schema::table('clinica_consultas', function (Blueprint $table) {
            $table->dropColumn(['id_evento', 'addendum']);
        });
    }
};

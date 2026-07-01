<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dte_documents', function (Blueprint $table) {
            $table->unsignedInteger('id_proyecto')->nullable()->after('destino');
            $table->unsignedBigInteger('id_categoria')->nullable()->after('id_proyecto');
            $table->string('tipo_gasto')->nullable()->after('id_categoria');
            $table->string('tipo_costo_gasto')->nullable()->after('tipo_gasto');
        });
    }

    public function down(): void
    {
        Schema::table('dte_documents', function (Blueprint $table) {
            $table->dropColumn(['id_proyecto', 'id_categoria', 'tipo_gasto', 'tipo_costo_gasto']);
        });
    }
};

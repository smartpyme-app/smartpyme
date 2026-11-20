<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consigna_movimientos', function (Blueprint $table) {
            $table->unsignedBigInteger('lote_id')->nullable()->after('id_bodega')->index();
        });
    }

    public function down(): void
    {
        Schema::table('consigna_movimientos', function (Blueprint $table) {
            $table->dropColumn('lote_id');
        });
    }
};

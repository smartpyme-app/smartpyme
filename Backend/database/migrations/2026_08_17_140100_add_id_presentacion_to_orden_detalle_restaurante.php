<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orden_detalle_restaurante', function (Blueprint $table) {
            $table->unsignedBigInteger('id_presentacion')->nullable()->after('producto_id');
            $table->foreign('id_presentacion')->references('id')->on('producto_presentaciones')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orden_detalle_restaurante', function (Blueprint $table) {
            $table->dropForeign(['id_presentacion']);
            $table->dropColumn('id_presentacion');
        });
    }
};

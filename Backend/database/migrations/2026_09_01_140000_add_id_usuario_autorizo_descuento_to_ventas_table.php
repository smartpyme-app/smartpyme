<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            if (! Schema::hasColumn('ventas', 'id_usuario_autorizo_descuento')) {
                $table->unsignedBigInteger('id_usuario_autorizo_descuento')->nullable()->after('id_usuario');
                $table->foreign('id_usuario_autorizo_descuento')->references('id')->on('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            if (Schema::hasColumn('ventas', 'id_usuario_autorizo_descuento')) {
                $table->dropForeign(['id_usuario_autorizo_descuento']);
                $table->dropColumn('id_usuario_autorizo_descuento');
            }
        });
    }
};

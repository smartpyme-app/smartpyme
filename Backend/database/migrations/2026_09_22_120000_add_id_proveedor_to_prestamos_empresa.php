<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prestamos_empresa', function (Blueprint $table) {
            $table->unsignedInteger('id_proveedor')->nullable()->after('id_usuario');
            $table->index(['id_empresa', 'id_proveedor']);
        });
    }

    public function down(): void
    {
        Schema::table('prestamos_empresa', function (Blueprint $table) {
            $table->dropIndex(['id_empresa', 'id_proveedor']);
            $table->dropColumn('id_proveedor');
        });
    }
};

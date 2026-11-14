<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proveedores', function (Blueprint $table) {
            $table->index(
                ['id_empresa', 'enable', 'nombre'],
                'idx_proveedores_empresa_enable_nombre'
            );
        });
    }

    public function down(): void
    {
        Schema::table('proveedores', function (Blueprint $table) {
            $table->dropIndex('idx_proveedores_empresa_enable_nombre');
        });
    }
};

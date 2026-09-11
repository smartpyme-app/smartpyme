<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categorias', function (Blueprint $table) {
            if (! Schema::hasColumn('categorias', 'mostrar_en_restaurante')) {
                $table->boolean('mostrar_en_restaurante')->default(true)->after('enable');
            }
        });
        Schema::table('productos', function (Blueprint $table) {
            if (! Schema::hasColumn('productos', 'mostrar_en_restaurante')) {
                $table->boolean('mostrar_en_restaurante')->default(true)->after('genera_comanda');
            }
        });
    }

    public function down(): void
    {
        Schema::table('categorias', function (Blueprint $table) {
            if (Schema::hasColumn('categorias', 'mostrar_en_restaurante')) {
                $table->dropColumn('mostrar_en_restaurante');
            }
        });
        Schema::table('productos', function (Blueprint $table) {
            if (Schema::hasColumn('productos', 'mostrar_en_restaurante')) {
                $table->dropColumn('mostrar_en_restaurante');
            }
        });
    }
};

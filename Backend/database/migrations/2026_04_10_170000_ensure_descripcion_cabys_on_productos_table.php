<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Asegura `descripcion_cabys` en `productos` (p. ej. si solo existía `codigo_cabys` o no se corrió la migración previa).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('productos', 'descripcion_cabys')) {
            return;
        }

        Schema::table('productos', function (Blueprint $table) {
            if (Schema::hasColumn('productos', 'codigo_cabys')) {
                $table->string('descripcion_cabys', 512)->nullable()->after('codigo_cabys');
            } else {
                $table->string('descripcion_cabys', 512)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('productos', 'descripcion_cabys')) {
            return;
        }

        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn('descripcion_cabys');
        });
    }
};

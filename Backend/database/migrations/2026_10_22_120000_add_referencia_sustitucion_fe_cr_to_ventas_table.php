<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            if (! Schema::hasColumn('ventas', 'clave_sustituida')) {
                $table->string('clave_sustituida', 50)->nullable();
            }
            if (! Schema::hasColumn('ventas', 'codigo_nota_anulacion')) {
                $table->string('codigo_nota_anulacion', 2)->nullable();
            }
            if (! Schema::hasColumn('ventas', 'clave_rechazada')) {
                $table->string('clave_rechazada', 50)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            foreach (['clave_sustituida', 'codigo_nota_anulacion', 'clave_rechazada'] as $columna) {
                if (Schema::hasColumn('ventas', $columna)) {
                    $table->dropColumn($columna);
                }
            }
        });
    }
};

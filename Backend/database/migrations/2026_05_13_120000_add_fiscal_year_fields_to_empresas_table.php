<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FK a catalogo_cuentas.id: debe ser el mismo tipo que la PK referenciada.
     * En BD actuales suele ser BIGINT UNSIGNED (p. ej. $table->id() / bigIncrements).
     * INT UNSIGNED aquí provoca SQLSTATE 3780 frente a BIGINT.
     */
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->unsignedTinyInteger('mes_inicio_ejercicio_fiscal')->default(1);
            $table->unsignedBigInteger('id_cuenta_cierre_resultados')->nullable();
        });

        Schema::table('empresas', function (Blueprint $table) {
            $table->foreign('id_cuenta_cierre_resultados')
                ->references('id')
                ->on('catalogo_cuentas')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropForeign(['id_cuenta_cierre_resultados']);
            $table->dropColumn(['mes_inicio_ejercicio_fiscal', 'id_cuenta_cierre_resultados']);
        });
    }
};

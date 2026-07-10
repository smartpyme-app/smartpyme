<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * En algunos entornos las migraciones 2017 de caja figuran como ejecutadas
 * pero las tablas no existen (p. ej. restauración parcial de BD).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cajas')) {
            Schema::create('cajas', function (Blueprint $table) {
                $table->increments('id');
                $table->string('nombre');
                $table->string('tipo');
                $table->text('descripcion')->nullable();
                $table->unsignedInteger('sucursal_id');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('caja_cortes')) {
            Schema::create('caja_cortes', function (Blueprint $table) {
                $table->increments('id');
                $table->decimal('saldo_inicial', 9, 2)->default(0);
                $table->decimal('saldo_final', 9, 2)->default(0);
                $table->dateTime('apertura');
                $table->dateTime('cierre')->nullable();
                $table->date('fecha');
                $table->unsignedInteger('caja_id');
                $table->unsignedInteger('supervisor_id')->nullable();
                $table->unsignedInteger('usuario_id');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // No revertir: podría borrar datos operativos en entornos reparados.
    }
};

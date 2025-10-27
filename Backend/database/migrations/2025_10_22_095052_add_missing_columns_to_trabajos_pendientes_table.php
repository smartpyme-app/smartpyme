<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMissingColumnsToTrabajosPendientesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('trabajos_pendientes', function (Blueprint $table) {
            // Agregar columnas faltantes
            $table->integer('prioridad')->default(1)->after('estado');
            $table->text('datos')->nullable()->after('parametros');
            $table->integer('intentos')->default(0)->after('datos');
            $table->integer('max_intentos')->default(3)->after('intentos');
            $table->timestamp('fecha_procesamiento')->nullable()->after('fecha_creacion');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('trabajos_pendientes', function (Blueprint $table) {
            // Eliminar columnas agregadas
            $table->dropColumn(['prioridad', 'datos', 'intentos', 'max_intentos', 'fecha_procesamiento']);
        });
    }
}

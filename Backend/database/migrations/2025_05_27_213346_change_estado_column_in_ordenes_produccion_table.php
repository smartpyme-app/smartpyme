<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeEstadoColumnInOrdenesProduccionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('ordenes_produccion', function (Blueprint $table) {
            $table->string('estado')->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ordenes_produccion', function (Blueprint $table) {
            $table->enum('estado', [
                'pendiente',
                'aceptada', 
                'en_proceso',
                'completada',
                'entregada',
                'anulada'
            ])->change();
        });
    }
}

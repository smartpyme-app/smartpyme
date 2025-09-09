<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPuntosFieldsToVentasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->integer('puntos_ganados')->nullable()->comment('nuevo');
            $table->integer('puntos_canjeados')->nullable()->comment('nuevo');
            $table->decimal('descuento_puntos', 8, 2)->nullable()->comment('nuevo');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ventas', function (Blueprint $table) {
            //
        });
    }
}

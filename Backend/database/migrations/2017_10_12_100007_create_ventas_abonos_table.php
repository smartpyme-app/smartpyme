<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateVentasAbonosTable extends Migration {

    public function up()
    {
        Schema::create('venta_abonos', function(Blueprint $table)
        {
            $table->increments('id');

            $table->date('fecha');
            $table->string('concepto');
            $table->string('estado');
            $table->string('metodo_pago');
            $table->string('detalle_banco')->nullable();
            $table->decimal('mora', 9,2)->default(0);
            $table->decimal('comision', 9,2)->default(0);
            $table->decimal('total', 9,2);
            $table->string('nota')->nullable();

            $table->integer('caja_id')->nullable();
            $table->integer('corte_id')->nullable();
            $table->integer('venta_id');
            $table->integer('cliente_id');
            $table->integer('usuario_id');
            $table->integer('sucursal_id');
            
            $table->timestamps();

        });
    }

    public function down()
    {
        Schema::drop('venta_abonos');
    }

}

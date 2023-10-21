<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProductoKardexTable extends Migration {

    public function up()
    {
        Schema::create('producto_kardex', function(Blueprint $table)
        {
            $table->increments('id');

            $table->date('fecha');
            $table->integer('producto_id');
            $table->integer('bodega_id');
            $table->string('detalle');
            $table->string('referencia')->nullable();
            $table->integer('entrada_cantidad')->nullable();
            $table->decimal('costo_unitario', 9,2)->nullable();
            $table->decimal('entrada_valor', 9,2)->nullable();
            $table->integer('salida_cantidad')->nullable();
            $table->decimal('precio_unitario', 9,2)->nullable();
            $table->decimal('salida_valor', 9,2)->nullable();
            $table->integer('total');
            $table->integer('usuario_id');

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::drop('producto_kardex');
    }

}

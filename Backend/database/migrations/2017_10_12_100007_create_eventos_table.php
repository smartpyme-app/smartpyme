<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEventosTable extends Migration {

	public function up()
	{
		Schema::create('eventos',function($table) {
            $table->increments('id');

            $table->date('fecha');
            $table->datetime('inicio');
            $table->datetime('fin');
            $table->string('estado');
            $table->string('categoria');
            $table->string('duracion');
            $table->string('frecuencia');
            $table->decimal('subtotal', 9,2);
            $table->decimal('iva', 9,2);
            $table->decimal('total', 9,2);
            $table->text('nota');
            $table->integer('cliente_id')->unsigned();
            $table->integer('usuario_id')->unsigned();
            $table->integer('sucursal_id')->unsigned();
            
            $table->timestamps();
        });
	}

	public function down()
	{
		Schema::drop('eventos');
	}

}

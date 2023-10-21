<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTransaccionesTable extends Migration {

    public function up()
    {
        Schema::create('transacciones', function(Blueprint $table)
        {
            $table->increments('id');

            $table->date('fecha');
            $table->string('correlativo')->nullable();
            $table->string('estado');
            $table->string('metodo_pago');
            $table->string('tipo_documento');
            $table->string('referencia')->nullable();
            $table->decimal('total', 9,2);
            $table->string('nota')->nullable();
            $table->integer('empresa_id');
            $table->integer('usuario_id');
            $table->timestamps();

        });
    }

    public function down()
    {
        Schema::drop('transacciones');
    }

}

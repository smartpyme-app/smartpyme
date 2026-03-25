<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAbonosGastosTable extends Migration {

    public function up()
    {
        Schema::create('abonos_gastos', function(Blueprint $table)
        {
            $table->increments('id');

            $table->date('fecha');
            $table->string('concepto');
            $table->string('referencia')->nullable();
            $table->string('estado');
            $table->string('nombre_de');
            $table->string('forma_pago');
            $table->string('detalle_banco')->nullable();
            $table->decimal('mora', 9,2)->default(0);
            $table->decimal('comision', 9,2)->default(0);
            $table->decimal('total', 9,2);
            $table->text('nota')->nullable();

            $table->integer('id_caja')->nullable();
            $table->integer('id_corte')->nullable();
            $table->integer('id_gasto');
            $table->unsignedBigInteger('id_usuario');
            $table->integer('id_sucursal');
            $table->unsignedInteger('id_empresa');
            
            $table->timestamps();

            $table->foreign('id_gasto')->references('id')->on('egresos')->onDelete('cascade');
            $table->foreign('id_usuario')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('id_sucursal')->references('id')->on('sucursales')->onDelete('restrict');
            $table->foreign('id_empresa')->references('id')->on('empresas')->onDelete('restrict');
        });
    }

    public function down()
    {
        Schema::drop('abonos_gastos');
    }

}


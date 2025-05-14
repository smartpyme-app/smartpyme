<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateClientesTable extends Migration {

	// public function up()
	// {
	// 	Schema::create('clientes', function(Blueprint $table)
	// 	{
	// 		$table->increments('id');

	// 		$table->string('nombre')->nullable();
    //         $table->string('empresa')->nullable();
	// 		$table->string('registro')->nullable()->unique();
	// 		$table->string('giro')->nullable();
    //         $table->string('tipo_contribuyente')->default('Pequeño');
	// 		$table->string('dui')->nullable()->unique();
	// 		$table->string('nit')->nullable()->unique();
	// 		$table->date('fecha_nacimiento')->nullable();
	// 		$table->string('direccion')->nullable();
	// 		$table->string('municipio')->nullable();
	// 		$table->string('departamento')->nullable();
	// 		$table->string('telefono')->nullable();
	// 		$table->string('correo')->nullable();
	// 		$table->string('sexo')->nullable();
	// 		$table->string('profesion')->nullable();
	// 		$table->string('estado_civil')->nullable();
	// 		$table->text('nota')->nullable();
	// 		$table->string('etiquetas')->nullable();
	// 		$table->integer('usuario_id');
	// 		$table->integer('empresa_id');

	// 		$table->softDeletes();
	// 		$table->timestamps();
	// 	});
	// }

	// public function down()
	// {
	// 	Schema::drop('clientes');
	// }

}

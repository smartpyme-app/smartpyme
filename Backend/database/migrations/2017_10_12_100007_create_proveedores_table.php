<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProveedoresTable extends Migration {

	// public function up()
	// {
	// 	Schema::create('proveedores', function(Blueprint $table)
	// 	{
	// 		$table->increments('id');

	// 		$table->string('nombre');
	// 		$table->string('dui')->nullable();
	// 		$table->string('nit')->nullable();
	// 		$table->string('registro')->nullable()->unique();
	// 		$table->string('giro')->nullable();
	// 		$table->string('descripcion')->nullable();
	// 		$table->string('direccion')->nullable();
	// 		$table->string('municipio')->nullable();
	// 		$table->string('departamento')->nullable();
	// 		$table->string('telefono')->nullable();
	// 		$table->string('tipo_contribuyente')->default('Pequeño');
	// 		$table->string('correo')->nullable();
	// 		$table->string('etiquetas')->nullable();
	// 		$table->string('nota')->nullable();
	// 		$table->integer('usuario_id');
	// 		$table->integer('empresa_id');

	// 		$table->softDeletes();
	// 		$table->timestamps();
	// 	});
	// }

	// public function down()
	// {
	// 	Schema::drop('proveedores');
	// }

}

<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProductosTable extends Migration {

	public function up()
	{
		Schema::create('productos', function(Blueprint $table)
		{
			$table->increments('id');

			$table->string('nombre');
			$table->text('descripcion')->nullable();
			$table->string('codigo')->nullable();
			$table->string('barcode')->nullable();
			$table->string('medida')->default('Unidad');
			$table->decimal('precio', 9,2)->default(0);

			$table->decimal('costo', 9,2)->default(0);
			$table->decimal('costo_anterior', 9,2)->default(0);
			
			$table->integer('id_categoria')->nullable();
			$table->string('marca')->nullable();
			$table->string('etiquetas')->nullable();

			$table->string('tipo')->default('Producto');

			$table->boolean('activo')->default(1);
			$table->integer('id_empresa');

			$table->softDeletes();
			$table->timestamps();
		});
	}

	public function down()
	{
		Schema::drop('productos');
	}

}

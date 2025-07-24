<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateGastosTable extends Migration {

	// public function up()
	// {
	// 	Schema::create('gastos', function(Blueprint $table)
	// 	{
	// 		$table->increments('id');

	// 		$table->date('fecha');
	// 		$table->string('referencia')->nullable();
    //         $table->string('descripcion')->nullable();
	// 		$table->string('categoria_id');
    //         $table->string('estado');
    //         $table->string('metodo_pago');
    //         $table->string('detalle_banco')->nullable();
    //         $table->string('condicion');
    //         $table->date('fecha_pago');
    //         $table->boolean('recurrente')->default(0);
    //         $table->date('fecha_recurrente')->nullable();
	// 		$table->decimal('subtotal', 9, 2);
	// 		$table->decimal('iva', 9, 2);
	// 		$table->decimal('total', 9, 2);
	// 		$table->integer('proveedor_id')->nullable();
    //         $table->integer('usuario_id');
	// 		$table->integer('sucursal_id');
	// 		$table->timestamps();

	// 	});
	// }

	// public function down()
	// {
	// 	Schema::drop('gastos');
	// }

}

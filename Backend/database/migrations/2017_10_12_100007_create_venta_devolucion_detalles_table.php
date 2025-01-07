<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateVentaDevolucionDetallesTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	// public function up()
	// {
	// 	Schema::create('venta_devolucion_detalles', function(Blueprint $table)
	// 	{
	// 		$table->increments('id');

	// 		$table->integer('producto_id')->unsigned();
	// 		$table->decimal('cantidad', 10,4);
	// 		$table->decimal('precio', 9,2);
	// 		$table->decimal('costo', 9,2);
	// 		$table->decimal('descuento', 9,2);
    //         $table->decimal('no_sujeta', 9,2)->default(0);
    //         $table->decimal('exenta', 9,2)->default(0);
    //         $table->decimal('gravada', 9,2)->default(0);
	// 		$table->decimal('subcosto', 9,2);
	// 		$table->decimal('subtotal', 9,2);
	// 		$table->decimal('iva', 9,2);
	// 		$table->decimal('total', 9,2);
	// 		$table->integer('devolucion_id');

	// 		$table->timestamps();
	// 	});
	// }

	// /**
	//  * Reverse the migrations.
	//  *
	//  * @return void
	//  */
	// public function down()
	// {
	// 	Schema::drop('venta_devolucion_detalles');
	// }

}

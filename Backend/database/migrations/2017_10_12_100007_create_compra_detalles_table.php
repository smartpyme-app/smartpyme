<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCompraDetallesTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	// public function up()
	// {
	// 	Schema::create('compra_detalles', function(Blueprint $table)
	// 	{
	// 		$table->increments('id');
			
	// 		$table->integer('producto_id');
	// 		$table->decimal('cantidad', 9,2);
	// 		$table->decimal('costo', 9,2);
	// 		$table->decimal('descuento', 5,2)->default(0);
    //         $table->decimal('no_sujeta', 9,2)->default(0);
    //         $table->decimal('exenta', 9,2)->default(0);
    //         $table->decimal('gravada', 9,2)->default(0);
	// 		$table->decimal('iva', 9,2)->default(0);
	// 		$table->decimal('subtotal', 9,2)->default(0);
	// 		$table->decimal('total', 9,2)->default(0);
	// 		$table->integer('compra_id');

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
	// 	Schema::drop('compra_detalles');
	// }

}

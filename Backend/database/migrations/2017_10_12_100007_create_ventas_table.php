<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateVentasTable extends Migration {

	// public function up()
	// {
	// 	Schema::create('ventas', function(Blueprint $table)
	// 	{
	// 		$table->increments('id');

	// 		$table->date('fecha');
	// 		$table->string('correlativo')->nullable();
	// 		$table->string('estado');
	// 		$table->string('tipo')->default('Interna');
	// 		$table->integer('canal_id');
	// 		$table->string('metodo_pago');
	// 		$table->string('detalle_banco')->nullable();
	// 		$table->string('tipo_documento');
    //         $table->string('condicion');
    //         $table->date('fecha_pago');
	// 		$table->string('referencia')->nullable();
	// 		$table->string('nota_remision')->nullable();
	// 		$table->string('num_pedido')->nullable();
	// 		$table->string('nombre')->nullable();
			
	// 		$table->decimal('recibido', 9,2)->nullable();
	// 		$table->decimal('subcosto', 9,2)->default(0);
	// 		$table->decimal('descuento', 9,2)->default(0);
	// 		$table->decimal('subtotal', 9,2);
    //         $table->decimal('no_sujeta', 9,2)->default(0);
    //         $table->decimal('exenta', 9,2)->default(0);
    //         $table->decimal('gravada', 9,2)->default(0);
	// 		$table->decimal('iva_percibido', 9,2)->default(0);
    //         $table->decimal('iva_retenido', 9,2)->default(0);
	// 		$table->decimal('iva', 9,2)->default(0);
    //         $table->decimal('total', 9,2);
    //         $table->string('nota')->nullable();

	// 		$table->integer('caja_id')->nullable();
    //         $table->integer('bodega_id')->nullable();
	// 		$table->integer('corte_id')->nullable();
	// 		$table->integer('cliente_id')->nullable();
	// 		$table->integer('usuario_id');
	// 		$table->integer('vendedor_id')->nullable();
	// 		$table->integer('sucursal_id');
			
	// 		$table->timestamps();

	// 	});
	// }

	// public function down()
	// {
	// 	Schema::drop('ventas');
	// }

}

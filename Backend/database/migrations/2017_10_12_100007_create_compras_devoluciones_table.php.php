<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateComprasDevolucionesTable extends Migration {

	public function up()
	{
		Schema::create('compras_devoluciones', function(Blueprint $table)
		{
			$table->increments('id');

			$table->date('fecha');
			$table->string('estado');
			$table->string('referencia')->nullable();
			$table->integer('proveedor_id');
			$table->decimal('descuento', 9,2);
			$table->decimal('subtotal', 9,2);
            $table->decimal('no_sujeta', 9,2)->default(0);
            $table->decimal('exenta', 9,2)->default(0);
            $table->decimal('gravada', 9,2)->default(0);    
            $table->decimal('iva_percibido', 9,4);
            $table->decimal('iva_retenido', 9,4);
			$table->decimal('iva', 9,2);
			$table->decimal('total', 9,2);
			$table->text('nota', 9,2)->nullable();
			$table->integer('compra_id');
            $table->integer('usuario_id');
			$table->integer('empresa_id');

			$table->timestamps();
		});
	}

	public function down()
	{
		Schema::drop('compras_devoluciones');
	}

}

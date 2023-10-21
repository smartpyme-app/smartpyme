<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateComprasTable extends Migration {

	public function up()
	{
		Schema::create('compras', function(Blueprint $table)
		{
			$table->increments('id');

			$table->date('fecha');
			$table->string('estado');
			$table->string('tipo')->default('Interna');
			$table->string('metodo_pago');
			$table->string('tipo_documento');
			$table->string('condicion');
			$table->date('fecha_pago');
			$table->string('num_referencia')->nullable();
			$table->string('num_serie')->nullable();
			$table->string('detalle_banco')->nullable();
			$table->string('num_orden_compra')->nullable();
			$table->string('notas')->nullable();
			$table->boolean('aplicada_inventario')->default(1);
			$table->integer('proveedor_id');
            $table->decimal('no_sujeta', 9,2)->default(0);
            $table->decimal('exenta', 9,2)->default(0);
            $table->decimal('gravada', 9,2)->default(0);	
			$table->decimal('iva_percibido', 9,4);
            $table->decimal('iva_retenido', 9,4);
			$table->decimal('descuento', 9,2);
			$table->decimal('iva', 9,2);
			$table->decimal('subtotal', 9,2);
			$table->decimal('total', 9,2);
            $table->integer('bodega_id')->nullable();
			$table->integer('usuario_id');
			$table->integer('empresa_id');

			$table->timestamps();
		});
	}

	public function down()
	{
		Schema::drop('compras');
	}

}

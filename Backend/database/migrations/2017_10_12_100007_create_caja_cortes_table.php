<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCajaCortesTable extends Migration {

	// public function up()
	// {
	// 	Schema::create('caja_cortes', function(Blueprint $table)
	// 	{
	// 		$table->increments('id');

	// 		$table->decimal('saldo_inicial', 9,2)->default(0);
	// 		$table->decimal('saldo_final', 9,2)->default(0);
	// 		$table->datetime('apertura');
	// 		$table->datetime('cierre')->nullable();
	// 		$table->date('fecha');
	// 		$table->integer('caja_id');
	// 		$table->integer('supervisor_id')->nullable();
	// 		$table->integer('usuario_id');

	// 		$table->timestamps();
	// 	});
	// }

	// public function down()
	// {
	// 	Schema::drop('caja_cortes');
	// }

}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDteTipoMapeoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('dte_tipo_mapeo', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_mh', 5)->comment('01, 03, 04, 05, 06, 11, etc.');
            $table->string('nombre_tipo')->comment('Nombre descriptivo del tipo DTE');
            $table->string('tipo_documento')->comment('Factura, Crédito fiscal, Nota de crédito, etc.');
            $table->enum('destino', ['compra', 'gasto'])->comment('Where to insert: compras or gastos table');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique('codigo_mh');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dte_tipo_mapeo');
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePreCuentaOrdenDetalleTable extends Migration
{
    /**
     * Para división por ítems: vincula cada orden_detalle a una pre_cuenta específica.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pre_cuenta_orden_detalle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pre_cuenta_id')->constrained('pre_cuentas_restaurante')->onDelete('cascade');
            $table->foreignId('orden_detalle_id')->constrained('orden_detalle_restaurante')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['pre_cuenta_id', 'orden_detalle_id'], 'pre_cuenta_orden_detalle_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pre_cuenta_orden_detalle');
    }
}

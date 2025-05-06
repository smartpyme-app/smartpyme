<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrdenPagosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ordenes_pago', function (Blueprint $table) {
            $table->id();
            $table->string('id_orden', 50);
            $table->unsignedBigInteger('id_usuario');
            $table->string('id_orden_n1co')->nullable();
            $table->string('id_autorizacion_3ds')->nullable();
            $table->string('autorizacion_url')->nullable();
            $table->unsignedBigInteger('id_plan');
            $table->string('payment_id')->nullable();
            $table->string('charge_id')->nullable();
            $table->string('item_id')->nullable();
            $table->string('nombre_cliente')->nullable();
            $table->string('email_cliente')->nullable();
            $table->string('telefono_cliente')->nullable();
            $table->string('plan')->nullable();
            $table->decimal('monto', 10, 2);
            $table->string('estado')->default('pendiente');
            $table->string('divisa')->default('USD');
            $table->string('codigo_autorizacion')->nullable();
            $table->dateTime('fecha_transaccion')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ordenes_pago');
    }
}

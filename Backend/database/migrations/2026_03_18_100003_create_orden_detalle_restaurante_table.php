<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrdenDetalleRestauranteTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('orden_detalle_restaurante', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sesion_id')->constrained('restaurante_sesiones_mesa')->onDelete('cascade');
            $table->unsignedInteger('producto_id');
            $table->decimal('cantidad', 10, 2)->default(1);
            $table->decimal('precio_unitario', 12, 2);
            $table->text('notas')->nullable()->comment('Nota del cliente ej: sin cebolla, término medio');
            $table->boolean('enviado_cocina')->default(false);
            $table->timestamps();

            $table->foreign('producto_id')->references('id')->on('productos')->onDelete('restrict');
            $table->index('sesion_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('orden_detalle_restaurante');
    }
}

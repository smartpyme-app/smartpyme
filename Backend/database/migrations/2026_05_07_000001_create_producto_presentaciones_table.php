<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductoPresentacionesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('producto_presentaciones', function (Blueprint $table) {
            $table->id();

            $table->unsignedInteger('id_producto');
            $table->foreign('id_producto')
                  ->references('id')
                  ->on('productos')
                  ->onDelete('cascade');

            $table->integer('id_unidad_medida');
            $table->foreign('id_unidad_medida')
                  ->references('id')
                  ->on('unidades');

            $table->string('nombre_comercial');

            // Cuántas unidades base contiene este empaque (ej: 40.000000 para una "Caja de 40")
            $table->decimal('factor_conversion', 16, 6)->default(1.000000);

            $table->decimal('precio_venta', 16, 6)->default(0.000000);

            $table->string('codigo_barras')->nullable();

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
        Schema::dropIfExists('producto_presentaciones');
    }
}

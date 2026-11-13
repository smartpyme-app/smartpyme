<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInventarioUbicacionesTable extends Migration
{
    public function up()
    {
        Schema::create('inventario_ubicaciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_inventario');
            $table->string('ubicacion', 100)->nullable();
            $table->string('ubicacion_especifica', 100)->nullable();
            $table->text('detalle')->nullable();
            $table->boolean('activa')->default(true);
            $table->decimal('cantidad', 10, 2)->nullable();
            $table->timestamps();

            $table->index('id_inventario');
        });
    }

    public function down()
    {
        Schema::dropIfExists('inventario_ubicaciones');
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transformacion_detalles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_transformacion');
            $table->unsignedBigInteger('id_producto');
            $table->decimal('cantidad', 10, 2);
            $table->string('tipo'); // ENTRADA o SALIDA
            $table->timestamps();

            $table->foreign('id_transformacion')
                  ->references('id')
                  ->on('transformaciones')
                  ->onDelete('cascade');
                  
            // $table->foreign('id_producto')->references('id')->on('productos');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transformacion_detalles');
    }
};

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
        Schema::create('cliente_categorias_preferidas', function (Blueprint $table) {
            $table->id();
            $table->integer('id_cliente');
            $table->foreign('id_cliente')->references('id')->on('clientes')->onDelete('cascade');
            

            $table->unsignedBigInteger('id_categoria')->nullable(); // Si tienes tabla de categorías
            $table->string('nombre_categoria', 100);
            
            $table->integer('cantidad_productos')->default(0);
            $table->integer('total_compras')->default(0);
            $table->decimal('total_gastado', 15, 2)->default(0);
            $table->decimal('porcentaje_gasto', 5, 2)->default(0);
            $table->tinyInteger('ranking')->default(0);
            
            $table->timestamps();
            
            // Índices
            $table->index(['id_cliente', 'ranking']);
            $table->index('id_cliente');
            
            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cliente_categorias_preferidas');
    }
};
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
        if (!Schema::hasTable('cliente_productos_top')) {
            Schema::create('cliente_productos_top', function (Blueprint $table) {
            $table->id();
            $table->integer('id_cliente');
$table->unsignedInteger('id_producto');
            
            $table->integer('total_cantidad')->default(0);
            $table->decimal('total_monto', 15, 2)->default(0);
            $table->integer('total_compras')->default(0);
            $table->date('ultima_compra')->nullable();
            $table->integer('dias_ultima_compra')->nullable();
            $table->decimal('precio_promedio', 15, 2)->default(0);
            
            // Para ranking
            $table->tinyInteger('ranking')->default(0); // 1-10 para top 10
            
            $table->timestamps();
            
            // Índices
            $table->index(['id_cliente', 'ranking']);
            $table->index('id_cliente');
            $table->index('id_producto');
            $table->unique(['id_cliente', 'id_producto']);
            
            $table->foreign('id_cliente')->references('id')->on('clientes')->onDelete('cascade');
            $table->foreign('id_producto')->references('id')->on('productos')->onDelete('cascade');
        });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cliente_productos_top');
    }
};
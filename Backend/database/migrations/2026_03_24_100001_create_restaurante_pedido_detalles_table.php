<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurante_pedido_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('restaurante_pedidos')->cascadeOnDelete();
            $table->unsignedInteger('producto_id');
            $table->decimal('cantidad', 14, 4);
            $table->decimal('precio', 14, 4);
            $table->decimal('descuento', 14, 4)->default(0);
            $table->decimal('subtotal', 14, 4);
            $table->decimal('total', 14, 4);
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->index('producto_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurante_pedido_detalles');
    }
};

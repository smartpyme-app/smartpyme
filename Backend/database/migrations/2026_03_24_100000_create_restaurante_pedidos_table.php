<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurante_pedidos', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_empresa');
            $table->unsignedInteger('id_sucursal')->nullable();
            $table->unsignedInteger('usuario_id');
            $table->date('fecha');
            $table->string('canal', 100)->nullable()->comment('Ej. Pedidos Ya');
            $table->string('referencia_externa', 150)->nullable()->comment('ID en plataforma externa');
            $table->enum('estado', ['borrador', 'pendiente_facturar', 'facturado', 'anulado'])->default('borrador');
            $table->unsignedInteger('id_venta')->nullable()->comment('Venta generada al procesar');
            $table->unsignedInteger('cliente_id')->nullable();
            $table->text('observaciones')->nullable();
            $table->decimal('subtotal', 14, 4)->default(0);
            $table->decimal('descuento', 14, 4)->default(0);
            $table->decimal('total', 14, 4)->default(0);
            $table->timestamps();

            $table->foreign('id_empresa')->references('id')->on('empresas')->onDelete('cascade');
            $table->foreign('id_venta')->references('id')->on('ventas')->nullOnDelete();
            $table->index(['id_empresa', 'fecha']);
            $table->index(['id_empresa', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurante_pedidos');
    }
};

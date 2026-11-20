<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consigna_movimientos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_empresa')->index();
            $table->dateTime('fecha');
            $table->string('tipo', 64);
            $table->decimal('cantidad', 16, 4);
            $table->unsignedBigInteger('id_producto')->index();
            $table->unsignedBigInteger('id_bodega')->index();
            $table->unsignedBigInteger('id_proveedor')->nullable()->index();
            $table->unsignedBigInteger('id_cliente')->nullable()->index();
            $table->decimal('costo_unitario', 16, 4)->nullable();
            $table->unsignedBigInteger('id_compra')->nullable();
            $table->unsignedBigInteger('id_detalle_compra')->nullable();
            $table->unsignedBigInteger('id_venta')->nullable();
            $table->unsignedBigInteger('id_detalle_venta')->nullable();
            $table->unsignedBigInteger('id_traslado')->nullable();
            $table->string('nota', 500)->nullable();
            $table->string('idempotency_key', 128)->nullable();
            $table->unsignedBigInteger('id_usuario')->nullable();
            $table->timestamps();

            $table->index(['id_empresa', 'fecha']);
            $table->index(['id_empresa', 'id_producto', 'id_bodega']);
            $table->unique(['id_empresa', 'idempotency_key'], 'consigna_movimientos_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consigna_movimientos');
    }
};

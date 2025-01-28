<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrdenPagoDetallesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ordenes_pago_detalles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('orden_pago_id');
            $table->string('item_id')->nullable();
            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->integer('quantity');
            $table->decimal('modifiers_total', 10, 2)->default(0);
            $table->string('sku')->nullable();
            $table->string('product_image_url')->nullable();
            $table->text('note')->nullable();
            $table->text('description')->nullable();
            $table->integer('quantity_available')->nullable();
            $table->boolean('requires_shipping')->default(false);
            $table->unsignedBigInteger('promo_id')->nullable();
            $table->decimal('promo_price', 10, 2)->nullable();
            $table->string('promo_name')->nullable();
            $table->timestamps();

            $table->foreign('orden_pago_id')
                ->references('id')
                ->on('ordenes_pago')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ordenes_pago_detalles');
    }
}

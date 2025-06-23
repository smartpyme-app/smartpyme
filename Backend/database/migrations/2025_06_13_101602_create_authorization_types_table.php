<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAuthorizationTypesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('authorization_types', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // 'purchase_orders_high_amount', 'sales_discount', etc
            $table->string('display_name'); // 'Órdenes de Compra > $10,000'
            $table->text('description')->nullable();
            $table->json('conditions')->nullable(); // {'amount_threshold': 10000}
            $table->integer('expiration_hours')->default(24);
            $table->boolean('active')->default(true);
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
        Schema::dropIfExists('authorization_types');
    }
}

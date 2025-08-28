<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->bigInteger('shopify_product_id')->nullable()->after('id');
            $table->bigInteger('shopify_variant_id')->nullable()->after('shopify_product_id');
            $table->bigInteger('shopify_inventory_item_id')->nullable()->after('shopify_variant_id');
        });
    }

    public function down()
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn([
                'shopify_product_id',
                'shopify_variant_id',
                'shopify_inventory_item_id'
            ]);
        });
    }
};
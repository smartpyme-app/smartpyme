<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->string('option1_name')->nullable()->after('nombre_variante');
            $table->string('option1_value')->nullable()->after('option1_name');
            $table->string('option2_name')->nullable()->after('option1_value');
            $table->string('option2_value')->nullable()->after('option2_name');
            $table->string('option3_name')->nullable()->after('option2_value');
            $table->string('option3_value')->nullable()->after('option3_name');
            $table->string('shopify_sku')->nullable()->after('shopify_inventory_item_id');
        });

        Schema::table('productos', function (Blueprint $table) {
            $table->index('shopify_product_id', 'idx_shopify_producto');
            $table->index('shopify_sku', 'idx_shopify_sku');
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropIndex('idx_shopify_producto');
            $table->dropIndex('idx_shopify_sku');
        });

        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn([
                'option1_name',
                'option1_value',
                'option2_name',
                'option2_value',
                'option3_name',
                'option3_value',
                'shopify_sku',
            ]);
        });
    }
};

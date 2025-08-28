<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->boolean('syncing_from_shopify')->default(false)->after('shopify_inventory_item_id');
            $table->timestamp('last_shopify_sync')->nullable()->after('syncing_from_shopify');
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn(['syncing_from_shopify', 'last_shopify_sync']);
        });
    }
};
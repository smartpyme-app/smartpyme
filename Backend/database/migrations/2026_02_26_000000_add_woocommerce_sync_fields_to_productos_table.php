<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->timestamp('last_woocommerce_sync')->nullable()->after('woocommerce_id');
            $table->boolean('imported_from_woocommerce_csv')->default(false)->after('last_woocommerce_sync');
            $table->unsignedBigInteger('woocommerce_parent_id')->nullable()->after('imported_from_woocommerce_csv')
                ->comment('ID del producto padre en WooCommerce, para variaciones');
        });
    }

    public function down()
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn([
                'last_woocommerce_sync',
                'imported_from_woocommerce_csv',
                'woocommerce_parent_id',
            ]);
        });
    }
};

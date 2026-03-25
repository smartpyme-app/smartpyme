<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('shopify_store_url')->nullable()->after('woocommerce_status');
            $table->string('shopify_consumer_secret')->nullable()->after('shopify_store_url');
            $table->string('shopify_webhook_secret')->nullable()->after('shopify_consumer_secret');
            $table->enum('shopify_status', ['connected', 'disconnected','connecting'])->default('disconnected')->after('shopify_webhook_secret');
            $table->unsignedBigInteger('shopify_canal_id')->nullable()->after('shopify_status');
            $table->integer('shopify_sync_progress')->default(0)->after('shopify_canal_id');
            $table->integer('shopify_sync_total_batches')->default(0)->after('shopify_sync_progress');
            $table->integer('shopify_sync_processed_batches')->default(0)->after('shopify_sync_total_batches');
            $table->enum('shopify_sync_status', ['idle', 'syncing', 'completed', 'error'])->default('idle')->after('shopify_sync_processed_batches');
            $table->timestamp('shopify_last_sync')->nullable()->after('shopify_sync_status');
            $table->text('shopify_error')->nullable()->after('shopify_last_sync');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->enum('shopify_status', ['connected', 'disconnected','connecting'])->default('disconnected')->after('woocommerce_status');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('shopify_status');
        });

        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn([
                'shopify_store_url',
                'shopify_consumer_secret',
                'shopify_webhook_secret',
                'shopify_status',
                'shopify_canal_id',
                'shopify_sync_progress',
                'shopify_sync_total_batches',
                'shopify_sync_processed_batches',
                'shopify_sync_status',
                'shopify_last_sync',
                'shopify_error'
            ]);
        });
    }
};

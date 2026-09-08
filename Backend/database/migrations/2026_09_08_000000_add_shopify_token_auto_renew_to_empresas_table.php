<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('shopify_client_id')->nullable()->after('shopify_store_url');
            $table->string('shopify_client_secret')->nullable()->after('shopify_client_id');
            $table->text('shopify_access_token')->nullable()->after('shopify_consumer_secret');
            $table->timestamp('shopify_token_expires_at')->nullable()->after('shopify_access_token');
        });
    }

    public function down()
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn([
                'shopify_client_id',
                'shopify_client_secret',
                'shopify_access_token',
                'shopify_token_expires_at',
            ]);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddShopifySyncBidirectionalToEmpresasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->boolean('shopify_sync_bidirectional')
            ->default(false)
            ->after('shopify_sync_total_batches')
            ->comment('Controla si los cambios se sincronizan bidireccionalmente con Shopify');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn('shopify_sync_bidirectional');
        });
    }
}

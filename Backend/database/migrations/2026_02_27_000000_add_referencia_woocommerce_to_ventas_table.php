<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('ventas', function (Blueprint $table) {
            if (!Schema::hasColumn('ventas', 'referencia_woocommerce')) {
                $table->string('referencia_woocommerce', 100)->nullable()->after('referencia_shopify');
                $table->unique(['referencia_woocommerce', 'id_empresa'], 'unique_referencia_woocommerce_empresa');
            }
        });
    }

    public function down()
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropUnique('unique_referencia_woocommerce_empresa');
            $table->dropColumn('referencia_woocommerce');
        });
    }
};

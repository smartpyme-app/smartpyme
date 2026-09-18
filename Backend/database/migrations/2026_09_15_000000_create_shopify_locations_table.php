<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('sucursales', 'shopify_location_id')) {
            Schema::table('sucursales', function (Blueprint $table) {
                $table->unsignedBigInteger('shopify_location_id')->nullable()->after('id_empresa');
                $table->index(['id_empresa', 'shopify_location_id']);
            });
        }

        if (!Schema::hasTable('shopify_locations')) {
            Schema::create('shopify_locations', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('id_empresa');
                $table->unsignedBigInteger('shopify_location_id');
                $table->string('shopify_location_name');
                $table->boolean('shopify_active')->default(true);

                $table->unsignedBigInteger('id_sucursal')->nullable();
                $table->unsignedBigInteger('id_bodega')->nullable();

                $table->boolean('sincronizar_stock')->default(true);
                $table->boolean('es_default')->default(false);

                $table->timestamps();

                $table->unique(['id_empresa', 'shopify_location_id'], 'empresa_shopify_loc_unique');
                $table->index(['id_empresa', 'id_bodega']);
                $table->index(['id_empresa', 'id_sucursal']);
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('shopify_locations');

        if (Schema::hasColumn('sucursales', 'shopify_location_id')) {
            Schema::table('sucursales', function (Blueprint $table) {
                $table->dropIndex(['id_empresa', 'shopify_location_id']);
                $table->dropColumn('shopify_location_id');
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('productos_imagenes', function (Blueprint $table) {
            $table->bigInteger('shopify_image_id')->nullable()->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('productos_imagenes', function (Blueprint $table) {
            $table->dropColumn('shopify_image_id');
        });
    }
};
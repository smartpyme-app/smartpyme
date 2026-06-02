<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('ventas', function (Blueprint $table) {
            if (!Schema::hasColumn('ventas', 'observaciones_shopify')) {
                $table->text('observaciones_shopify')->nullable()->after('observaciones');
            }
        });
    }

    public function down()
    {
        Schema::table('ventas', function (Blueprint $table) {
            if (Schema::hasColumn('ventas', 'observaciones_shopify')) {
                $table->dropColumn('observaciones_shopify');
            }
        });
    }
};

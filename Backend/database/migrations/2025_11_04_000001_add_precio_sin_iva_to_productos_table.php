<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPrecioSinIvaToProductosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->decimal('precio_sin_iva', 10, 4)->nullable()->after('precio');
            $table->decimal('precio_con_iva', 10, 4)->nullable()->after('precio_sin_iva');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn(['precio_sin_iva', 'precio_con_iva']);
        });
    }
}

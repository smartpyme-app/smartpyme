<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNombreComercialToProductosTable extends Migration
{
    public function up()
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->string('nombre_comercial')->nullable()->after('nombre');
        });
    }

    public function down()
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn('nombre_comercial');
        });
    }
}

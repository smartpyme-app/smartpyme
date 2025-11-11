<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPruebaMasivaToEgresosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('egresos', function (Blueprint $table) {
            $table->boolean('prueba_masiva')->default(false)->after('dte_invalidacion');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('egresos', function (Blueprint $table) {
            $table->dropColumn('prueba_masiva');
        });
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAliadoToEmpresasAndPromocionalesTable extends Migration
{
    public function up()
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('aliado')->nullable()->after('campania');
        });

        Schema::table('promocionales', function (Blueprint $table) {
            $table->string('aliado')->nullable()->after('campania');
        });
    }

    public function down()
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn('aliado');
        });

        Schema::table('promocionales', function (Blueprint $table) {
            $table->dropColumn('aliado');
        });
    }
}

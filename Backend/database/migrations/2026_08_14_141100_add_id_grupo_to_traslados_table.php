<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIdGrupoToTrasladosTable extends Migration
{
    public function up()
    {
        Schema::table('traslados', function (Blueprint $table) {
            $table->uuid('id_grupo')->nullable()->after('concepto');
            $table->index('id_grupo');
        });
    }

    public function down()
    {
        Schema::table('traslados', function (Blueprint $table) {
            $table->dropIndex(['id_grupo']);
            $table->dropColumn('id_grupo');
        });
    }
}

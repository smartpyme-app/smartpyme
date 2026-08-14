<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNumeroEmisionToDocumentosTable extends Migration
{
    public function up()
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->char('numero_emision', 2)->nullable()->after('correlativo');
        });
    }

    public function down()
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->dropColumn('numero_emision');
        });
    }
}

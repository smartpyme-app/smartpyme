<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddCodigoMhToImpuestosTable extends Migration
{
    public function up()
    {
        Schema::table('impuestos', function (Blueprint $table) {
            $table->string('codigo_mh', 5)->nullable()->after('porcentaje');
        });

        if (Schema::hasTable('impuestos')) {
            DB::table('impuestos')
                ->whereNull('codigo_mh')
                ->where('porcentaje', 13)
                ->update(['codigo_mh' => '20']);
        }
    }

    public function down()
    {
        Schema::table('impuestos', function (Blueprint $table) {
            $table->dropColumn('codigo_mh');
        });
    }
}

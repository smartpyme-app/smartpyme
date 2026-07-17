<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLoteIdToKardexsTable extends Migration
{
    public function up()
    {
        Schema::table('kardexs', function (Blueprint $table) {
            if (!Schema::hasColumn('kardexs', 'lote_id')) {
                $table->unsignedInteger('lote_id')->nullable()->after('id_producto');
                $table->index('lote_id');
            }
        });
    }

    public function down()
    {
        Schema::table('kardexs', function (Blueprint $table) {
            if (Schema::hasColumn('kardexs', 'lote_id')) {
                $table->dropIndex(['lote_id']);
                $table->dropColumn('lote_id');
            }
        });
    }
}

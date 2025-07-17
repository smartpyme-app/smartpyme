<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIdAuthorizationToOrdenComprasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('orden_compras', function (Blueprint $table) {
            $table->unsignedBigInteger('id_authorization')->nullable()->after('id_proveedor');
            $table->foreign('id_authorization')->references('id')->on('authorizations')->onDelete('set null');
            $table->index('id_authorization');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('orden_compras', function (Blueprint $table) {
            $table->dropForeign(['id_authorization']);
            $table->dropIndex(['id_authorization']);
            $table->dropColumn('id_authorization');
        });
    }
}

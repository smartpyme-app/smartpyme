<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIdEmpresaToRolesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->unsignedInteger('id_empresa')->nullable()->after('name');
            $table->foreign('id_empresa')->references('id')->on('empresas')->onDelete('cascade');
            $table->index(['id_empresa', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropForeign(['id_empresa']);
            $table->dropIndex(['id_empresa', 'name']);
            $table->dropColumn('id_empresa');
        });
    }
}

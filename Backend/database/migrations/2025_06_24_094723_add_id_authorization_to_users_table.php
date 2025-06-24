<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('id_authorization')->nullable()->after('id_sucursal');
            $table->foreign('id_authorization')->references('id')->on('authorizations')->onDelete('set null');
            $table->index('id_authorization');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['id_authorization']);
            $table->dropIndex(['id_authorization']);
            $table->dropColumn('id_authorization');
        });
    }
};
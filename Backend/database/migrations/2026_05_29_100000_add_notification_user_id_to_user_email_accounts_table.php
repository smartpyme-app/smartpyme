<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNotificationUserIdToUserEmailAccountsTable extends Migration
{
    public function up()
    {
        Schema::table('user_email_accounts', function (Blueprint $table) {
            $table->unsignedBigInteger('notification_user_id')
                ->nullable()
                ->after('user_id')
                ->comment('Usuario que recibe alertas de DTEs por revisar');

            $table->foreign('notification_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('user_email_accounts', function (Blueprint $table) {
            $table->dropForeign(['notification_user_id']);
            $table->dropColumn('notification_user_id');
        });
    }
}

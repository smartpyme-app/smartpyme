<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddForwardToUserEmailAccountsProvider extends Migration
{
    public function up()
    {
        DB::statement("ALTER TABLE user_email_accounts MODIFY COLUMN provider ENUM('gmail', 'outlook', 'imap', 'forward') NOT NULL");
    }

    public function down()
    {
        DB::statement("ALTER TABLE user_email_accounts MODIFY COLUMN provider ENUM('gmail', 'outlook', 'imap') NOT NULL");
    }
}

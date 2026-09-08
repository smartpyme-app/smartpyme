<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddVerificationToEmailInboxes extends Migration
{
    public function up()
    {
        Schema::table('email_inboxes', function (Blueprint $table) {
            $table->string('verification_code', 32)->nullable()->after('revoked_at');
            $table->string('verification_link', 500)->nullable()->after('verification_code');
            $table->timestamp('verification_received_at')->nullable()->after('verification_link');
        });
    }

    public function down()
    {
        Schema::table('email_inboxes', function (Blueprint $table) {
            $table->dropColumn(['verification_code', 'verification_link', 'verification_received_at']);
        });
    }
}

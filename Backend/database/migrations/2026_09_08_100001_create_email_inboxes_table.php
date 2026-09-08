<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEmailInboxesTable extends Migration
{
    public function up()
    {
        Schema::create('email_inboxes', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_empresa');
            $table->unsignedBigInteger('user_email_account_id');
            $table->string('token', 64);
            $table->string('email');
            $table->string('status', 32)->default('ACTIVE');
            $table->string('purpose', 32)->default('dte');
            $table->timestamp('last_email_at')->nullable();
            $table->timestamp('last_dte_at')->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->string('last_error_message', 500)->nullable();
            $table->unsignedInteger('emails_received')->default(0);
            $table->unsignedInteger('emails_rejected')->default(0);
            $table->unsignedInteger('dtes_imported')->default(0);
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique('token');
            $table->unique('email');
            $table->index(['id_empresa', 'status']);
            $table->foreign('id_empresa')->references('id')->on('empresas')->onDelete('cascade');
            $table->foreign('user_email_account_id')->references('id')->on('user_email_accounts')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('email_inboxes');
    }
}

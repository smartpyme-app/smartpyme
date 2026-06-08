<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSyncLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sync_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_empresa');
            $table->unsignedBigInteger('user_email_account_id');
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->enum('status', ['running', 'completed', 'failed'])->default('running');
            $table->integer('emails_scanned')->default(0);
            $table->integer('dtes_found')->default(0);
            $table->integer('dtes_processed')->default(0);
            $table->integer('dtes_failed')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->foreign('id_empresa')->references('id')->on('empresas')->onDelete('cascade');
            $table->foreign('user_email_account_id')->references('id')->on('user_email_accounts')->onDelete('cascade');
            $table->index(['user_email_account_id', 'started_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sync_logs');
    }
}

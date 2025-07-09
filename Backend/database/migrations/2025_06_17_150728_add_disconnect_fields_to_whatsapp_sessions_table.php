<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDisconnectFieldsToWhatsappSessionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            $table->string('disconnected_by')->nullable();
            $table->timestamp('disconnected_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            $table->dropColumn('disconnected_by');
            $table->dropColumn('disconnected_at');
        });
    }
}

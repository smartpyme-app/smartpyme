<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBoxfulCredentialsToEmpresasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('boxful_client_id')->nullable()->after('correo');
            $table->string('boxful_email')->nullable()->after('boxful_client_id');
            $table->text('boxful_password')->nullable()->after('boxful_email');
            $table->text('boxful_access_token')->nullable()->after('boxful_password');
            $table->timestamp('boxful_token_expires_at')->nullable()->after('boxful_access_token');
            $table->string('boxful_status')->default('disconnected')->after('boxful_token_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @var void
     */
    public function down()
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn([
                'boxful_client_id',
                'boxful_email', 
                'boxful_password', 
                'boxful_access_token', 
                'boxful_token_expires_at', 
                'boxful_status'
            ]);
        });
    }
}

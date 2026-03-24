<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserEmailAccountsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_email_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_empresa');
            $table->unsignedBigInteger('user_id');
            $table->enum('provider', ['gmail', 'outlook', 'imap']);
            $table->string('email');
            $table->text('access_token')->nullable()->comment('Encrypted - OAuth only');
            $table->text('refresh_token')->nullable()->comment('Encrypted - OAuth only');
            $table->timestamp('token_expires_at')->nullable();
            $table->string('imap_host')->nullable();
            $table->integer('imap_port')->nullable();
            $table->string('imap_encryption')->nullable()->comment('ssl, tls, starttls');
            $table->string('imap_user')->nullable();
            $table->text('imap_password')->nullable()->comment('Encrypted - IMAP only');
            $table->unsignedInteger('id_sucursal')->nullable();
            $table->unsignedInteger('id_bodega')->nullable();
            $table->boolean('actualizar_inventario')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();

            $table->unique(['id_empresa', 'email', 'provider'], 'user_email_accounts_empresa_email_provider_unique');
            $table->foreign('id_empresa')->references('id')->on('empresas')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_email_accounts');
    }
}

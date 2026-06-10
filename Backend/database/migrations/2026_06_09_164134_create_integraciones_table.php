<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIntegracionesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('integraciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_empresa')->unique();
            
            $table->string('boxful_client_id')->nullable();
            $table->string('boxful_email')->nullable();
            $table->text('boxful_password')->nullable();
            $table->text('boxful_access_token')->nullable();
            $table->timestamp('boxful_token_expires_at')->nullable();
            $table->string('boxful_status')->default('disconnected');
            
            $table->timestamps();

            $table->foreign('id_empresa')->references('id')->on('empresas')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('integraciones');
    }
}

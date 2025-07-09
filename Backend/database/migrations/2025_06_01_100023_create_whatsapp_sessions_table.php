<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWhatsappSessionsTable extends Migration
{
    public function up()
    {
        Schema::create('whatsapp_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('whatsapp_number', 20)->unique();
            $table->unsignedInteger('id_empresa')->nullable();
            $table->unsignedInteger('id_usuario')->nullable(); // ⭐ NUEVO
            $table->enum('status', ['pending_code', 'pending_user', 'connected'])->default('pending_code');
            $table->integer('code_attempts')->default(0);
            $table->integer('user_attempts')->default(0); // ⭐ NUEVO
            $table->timestamp('last_message_at')->nullable();
            $table->json('session_data')->nullable();
            $table->timestamps();
            
            $table->foreign('id_empresa')->references('id')->on('empresas')->onDelete('cascade');
           // $table->foreign('id_usuario')->references('id')->on('users')->onDelete('cascade');
            $table->index(['whatsapp_number', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('whatsapp_sessions');
    }
}
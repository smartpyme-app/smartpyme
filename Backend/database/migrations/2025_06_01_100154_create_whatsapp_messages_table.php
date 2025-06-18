<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWhatsappMessagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->string('whatsapp_number', 20);
            $table->unsignedInteger('id_empresa')->nullable();
            $table->unsignedInteger('id_usuario')->nullable();
            $table->enum('message_type', ['incoming', 'outgoing']);
            $table->text('message_content');
            $table->boolean('is_bot_response')->default(false);
            $table->json('metadata')->nullable(); // Para guardar datos extra del mensaje
            $table->timestamps();
            
            // Índices para optimizar consultas
            $table->index(['whatsapp_number', 'created_at']);
            $table->index(['id_empresa', 'created_at']);
            $table->index('message_type');
            
            // Claves foráneas
            $table->foreign('id_empresa')->references('id')->on('empresas')->onDelete('cascade');
            //$table->foreign('id_usuario')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('whatsapp_messages');
    }
}
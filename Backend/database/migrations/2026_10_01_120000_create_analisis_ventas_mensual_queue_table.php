<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analisis_ventas_mensual_queue', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->unsignedBigInteger('id_empresa');
            $table->unsignedBigInteger('id_usuario')->nullable();
            $table->json('params');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('id_empresa');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analisis_ventas_mensual_queue');
    }
};

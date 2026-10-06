<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contador_empresa_accesos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_usuario_contador');
            $table->unsignedInteger('id_empresa');
            $table->string('estado', 20)->default('activo');
            $table->json('permisos')->nullable();
            $table->unsignedBigInteger('invitado_por_user_id')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['id_usuario_contador', 'id_empresa'], 'contador_empresa_accesos_unique');
            $table->foreign('id_usuario_contador')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('id_empresa')->references('id')->on('empresas')->cascadeOnDelete();
            $table->foreign('invitado_por_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contador_empresa_accesos');
    }
};

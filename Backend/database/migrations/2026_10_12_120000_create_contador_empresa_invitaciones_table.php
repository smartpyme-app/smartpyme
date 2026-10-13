<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contador_empresa_invitaciones', function (Blueprint $table) {
            $table->id();
            $table->string('token_hash', 64)->unique();
            $table->string('email_admin', 255);
            $table->unsignedInteger('id_empresa_cliente');
            $table->unsignedInteger('id_empresa_despacho');
            $table->unsignedBigInteger('id_usuario_invito');
            $table->string('estado', 20)->default('pendiente');
            $table->timestamp('expira_en');
            $table->timestamp('respondida_en')->nullable();
            $table->timestamps();

            $table->index(['id_empresa_despacho', 'estado']);
            $table->index(['id_empresa_cliente', 'estado']);
            $table->foreign('id_empresa_cliente')->references('id')->on('empresas')->cascadeOnDelete();
            $table->foreign('id_empresa_despacho')->references('id')->on('empresas')->cascadeOnDelete();
            $table->foreign('id_usuario_invito')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contador_empresa_invitaciones');
    }
};

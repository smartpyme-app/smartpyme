<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Almacenamiento DB de Idempotency-Key para POST/PUT críticos de Restaurante.
 * Redis no es fuente de verdad: integridad vía unique + lock en esta tabla.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('restaurante_idempotency_keys')) {
            return;
        }

        Schema::create('restaurante_idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_empresa');
            $table->unsignedBigInteger('user_id');
            $table->string('operation', 64);
            $table->string('idempotency_key', 128);
            $table->string('status', 16); // processing|completed
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->mediumText('response_body')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['id_empresa', 'user_id', 'operation', 'idempotency_key'],
                'uq_rest_idempotency'
            );
            $table->index(['expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurante_idempotency_keys');
    }
};

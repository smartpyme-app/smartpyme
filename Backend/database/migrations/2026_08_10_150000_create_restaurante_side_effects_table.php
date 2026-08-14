<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Outbox de side-effects no críticos (impresión/notif). MariaDB = SoT de “ya procesado”.
 * Queue/Redis no sustituyen esta tabla para idempotencia.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('restaurante_side_effects')) {
            return;
        }

        Schema::create('restaurante_side_effects', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_empresa');
            $table->string('type', 64);
            $table->string('resource_type', 32);
            $table->unsignedBigInteger('resource_id');
            $table->string('status', 16)->default('pending'); // pending|processing|done|failed
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->json('payload')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['type', 'resource_type', 'resource_id'], 'rest_side_effects_type_resource_uq');
            $table->index(['id_empresa', 'status'], 'rest_side_effects_empresa_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurante_side_effects');
    }
};

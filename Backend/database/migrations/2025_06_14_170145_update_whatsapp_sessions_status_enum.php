<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Actualizar el enum para incluir 'pending_verification'
        DB::statement("ALTER TABLE whatsapp_sessions MODIFY COLUMN status ENUM('pending_code', 'pending_user', 'pending_verification', 'connected') DEFAULT 'pending_code'");
    }

    public function down(): void
    {
        // Volver al enum original
        DB::statement("ALTER TABLE whatsapp_sessions MODIFY COLUMN status ENUM('pending_code', 'pending_user', 'connected') DEFAULT 'pending_code'");
    }
};
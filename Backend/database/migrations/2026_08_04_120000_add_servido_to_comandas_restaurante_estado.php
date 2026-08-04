<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('comandas_restaurante')) {
            return;
        }

        // MySQL/MariaDB enum: añadir estado «servido» (comanda ya entregada a mesa).
        DB::statement("ALTER TABLE comandas_restaurante MODIFY COLUMN estado ENUM('pendiente', 'preparando', 'listo', 'servido') NOT NULL DEFAULT 'pendiente'");
    }

    public function down(): void
    {
        if (! Schema::hasTable('comandas_restaurante')) {
            return;
        }

        DB::table('comandas_restaurante')->where('estado', 'servido')->update(['estado' => 'listo']);
        DB::statement("ALTER TABLE comandas_restaurante MODIFY COLUMN estado ENUM('pendiente', 'preparando', 'listo') NOT NULL DEFAULT 'pendiente'");
    }
};

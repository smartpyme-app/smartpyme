<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->boolean('webhook_paquete_venta_enabled')->default(false);
            $table->string('webhook_paquete_venta_url', 2048)->nullable();
            $table->string('webhook_paquete_venta_secret', 255)->nullable();
            $table->text('webhook_paquete_venta_bearer_token')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn([
                'webhook_paquete_venta_enabled',
                'webhook_paquete_venta_url',
                'webhook_paquete_venta_secret',
                'webhook_paquete_venta_bearer_token',
            ]);
        });
    }
};

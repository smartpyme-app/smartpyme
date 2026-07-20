<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIntegracionesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('integraciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_empresa');
            $table->string('proveedor', 50); // 'boxful', 'shopify', 'wompi', etc.

            // Una sola fuente de verdad para el estado
            $table->string('estado', 20)->default('disconnected');
            // Valores: disconnected | connected | error | expired | pending

            // Config NO sensible: webhooks, preferencias, URLs del shop, etc.
            $table->json('configuracion')->nullable();

            // Credenciales estáticas encriptadas: client_id, client_secret, api_key
            // Cambian solo cuando el usuario las reconfigura
            $table->text('credenciales')->nullable();

            // Token de acceso separado: se refresca frecuentemente (OAuth2)
            // Null si el proveedor usa API key directa (no necesita token)
            $table->text('access_token')->nullable();         // encriptado vía cast
            $table->text('refresh_token')->nullable();        // encriptado vía cast
            $table->timestamp('token_expires_at')->nullable();

            // Trazabilidad operacional (sin llenar configuracion de ruido)
            $table->timestamp('last_sync_at')->nullable();
            $table->text('last_error')->nullable();

            $table->timestamps();

            $table->unique(['id_empresa', 'proveedor']);
            $table->foreign('id_empresa')
                  ->references('id')->on('empresas')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('integraciones');
    }
}

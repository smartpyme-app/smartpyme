<?php

use App\Http\Controllers\Api\WhatsApp\WebhookController;
use Illuminate\Support\Facades\Route;


Route::prefix('whatsapp')->group(function () {

    Route::get('/webhook', [WebhookController::class, 'verify']);
    

    Route::post('/webhook', [WebhookController::class, 'handle']);
});

Route::middleware(['auth:api'])->prefix('admin/whatsapp')->group(function () {
    // Estadísticas de WhatsApp
    Route::get('/stats', [WebhookController::class, 'getStats']);
    
    // Sesiones activas
    Route::get('/sessions', [WebhookController::class, 'getSessions']);
    
    // Enviar mensaje manual (para testing)
    Route::post('/send', [WebhookController::class, 'sendManualMessage']);
});
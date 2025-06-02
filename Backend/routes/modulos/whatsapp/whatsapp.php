<?php

use App\Http\Controllers\Api\WhatsApp\WebhookController;
use Illuminate\Support\Facades\Route;


Route::prefix('whatsapp')->group(function () {

    Route::get('/webhook', [WebhookController::class, 'verify']);


    Route::post('/webhook', [WebhookController::class, 'handle']);
});

Route::middleware(['auth:api'])->prefix('admin/whatsapp')->group(function () {
    Route::get('/stats', [WebhookController::class, 'getStats']);
    Route::get('/sessions', [WebhookController::class, 'getSessions']);
    Route::delete('/sessions/{id}', [WebhookController::class, 'disconnectSession']);
    Route::get('/sessions/{id}/messages', [WebhookController::class, 'getSessionMessages']);
});

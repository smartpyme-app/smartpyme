<?php

use App\Http\Controllers\Api\DteManagement\EmailAccountController;
use App\Http\Controllers\Api\DteManagement\EmailInboxController;
use App\Http\Controllers\Api\DteManagement\GmailAuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| DTE Management - Email Accounts (requires jwt.auth)
|--------------------------------------------------------------------------
| Gmail callback is in api.php, outside this group.
*/

Route::group(['middleware' => ['jwt.auth', 'verificar.funcionalidad:descarga-automatizada-dtes'], 'prefix' => 'email-accounts'], function () {
    Route::get('/', [EmailAccountController::class, 'index']);
    Route::get('/gmail/redirect', [GmailAuthController::class, 'redirect']);
    Route::post('/imap/test', [EmailAccountController::class, 'testImap']);
    Route::post('/imap', [EmailAccountController::class, 'storeImap']);
    Route::post('/{id}/sync', [EmailAccountController::class, 'sync']);
    Route::post('/{id}/notificaciones', [EmailAccountController::class, 'updateNotificaciones']);
    Route::delete('/{id}', [EmailAccountController::class, 'destroy']);
});

Route::group(['middleware' => ['jwt.auth', 'verificar.funcionalidad:descarga-automatizada-dtes'], 'prefix' => 'email-inboxes'], function () {
    Route::get('/', [EmailInboxController::class, 'show']);
    Route::post('/', [EmailInboxController::class, 'store']);
    Route::post('/{id}/pause', [EmailInboxController::class, 'pause']);
    Route::post('/{id}/resume', [EmailInboxController::class, 'resume']);
    Route::post('/{id}/regenerate', [EmailInboxController::class, 'regenerate']);
    Route::delete('/{id}', [EmailInboxController::class, 'destroy']);
});

<?php

use App\Http\Controllers\Api\DteManagement\EmailAccountController;
use App\Http\Controllers\Api\DteManagement\GmailAuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| DTE Management - Email Accounts (requires jwt.auth)
|--------------------------------------------------------------------------
| Gmail callback is in api.php, outside this group.
*/

Route::group(['middleware' => ['jwt.auth'], 'prefix' => 'email-accounts'], function () {
    Route::get('/', [EmailAccountController::class, 'index']);
    Route::get('/gmail/redirect', [GmailAuthController::class, 'redirect']);
    Route::post('/imap/test', [EmailAccountController::class, 'testImap']);
    Route::post('/imap', [EmailAccountController::class, 'storeImap']);
    Route::post('/{id}/sync', [EmailAccountController::class, 'sync']);
    Route::delete('/{id}', [EmailAccountController::class, 'destroy']);
});

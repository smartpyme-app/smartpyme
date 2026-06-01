<?php

use App\Http\Controllers\Api\DteManagement\SyncLogController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| DTE Management - Sync Logs
|--------------------------------------------------------------------------
*/

Route::group(['middleware' => ['jwt.auth', 'verificar.funcionalidad:descarga-automatizada-dtes'], 'prefix' => 'sync-logs'], function () {
    Route::get('/', [SyncLogController::class, 'index']);
});

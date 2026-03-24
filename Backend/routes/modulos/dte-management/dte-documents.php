<?php

use App\Http\Controllers\Api\DteManagement\DteDocumentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| DTE Management - DTE Documents
|--------------------------------------------------------------------------
*/

Route::group(['middleware' => ['jwt.auth'], 'prefix' => 'dtes'], function () {
    Route::get('/', [DteDocumentController::class, 'index']);
    Route::get('/{id}', [DteDocumentController::class, 'show']);
    Route::patch('/{id}', [DteDocumentController::class, 'update']);
    Route::post('/{id}/procesar', [DteDocumentController::class, 'procesar']);
    Route::get('/{id}/download/json', [DteDocumentController::class, 'downloadJson']);
    Route::get('/{id}/download/pdf', [DteDocumentController::class, 'downloadPdf']);
});

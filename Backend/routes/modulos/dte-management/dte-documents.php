<?php

use App\Http\Controllers\Api\DteManagement\DteDocumentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| DTE Management - DTE Documents
|--------------------------------------------------------------------------
*/

Route::group(['middleware' => ['jwt.auth', 'verificar.funcionalidad:descarga-automatizada-dtes'], 'prefix' => 'dtes'], function () {
    Route::get('/', [DteDocumentController::class, 'index']);
    Route::get('/pending-review-alert', [DteDocumentController::class, 'pendingReviewAlert']);
    Route::get('/{id}', [DteDocumentController::class, 'show']);
    Route::put('/{id}', [DteDocumentController::class, 'update']);
    Route::patch('/{id}', [DteDocumentController::class, 'update']);
    Route::post('/{id}/procesar', [DteDocumentController::class, 'procesar']);
    Route::post('/{id}/anular', [DteDocumentController::class, 'anular']);
    Route::get('/{id}/download/json', [DteDocumentController::class, 'downloadJson']);
    Route::get('/{id}/download/xml', [DteDocumentController::class, 'downloadXml']);
    Route::get('/{id}/download/acuse', [DteDocumentController::class, 'downloadAcuse']);
    Route::get('/{id}/download/pdf', [DteDocumentController::class, 'downloadPdf']);
});

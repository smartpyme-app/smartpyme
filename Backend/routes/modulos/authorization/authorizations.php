<?php

use App\Http\Controllers\Api\Authorization\AuthorizationController;
use App\Http\Controllers\Api\Authorization\AuthorizationTypeController;
use Illuminate\Support\Facades\Route;

// Route::prefix('authorizations')->group(function () {
    Route::middleware(['company_scope'])->prefix('authorizations')->group(function () {
    Route::get('/', [AuthorizationController::class, 'index']);
    Route::get('/pending', [AuthorizationController::class, 'pending']);
    Route::post('/request', [AuthorizationController::class, 'request']);
    Route::post('/check-requirement', [AuthorizationController::class, 'checkRequirement']);
    Route::get('/{code}', [AuthorizationController::class, 'show']);
    Route::post('/{code}/approve', [AuthorizationController::class, 'approve']);
    Route::post('/{code}/reject', [AuthorizationController::class, 'reject']);
    Route::get('/history/{modelType}/{modelId}', [AuthorizationController::class, 'history']);
});

// Rutas de tipos de autorización (solo super admin)
Route::middleware(['role:super_admin'])->prefix('authorization-types')->group(function () {
    Route::get('/', [AuthorizationTypeController::class, 'index']);
    Route::post('/', [AuthorizationTypeController::class, 'store']);
    Route::get('/{typeId}/users', [AuthorizationTypeController::class, 'getUsers']);
    Route::get('/{typeId}/available-users', [AuthorizationTypeController::class, 'availableUsers']);
    Route::post('/{typeId}/assign-users', [AuthorizationTypeController::class, 'assignUsers']);
});

// Ruta pública para ver autorización (para email)
Route::get('/authorization/{code}', function ($code) {
    return redirect(env('APP_URL') . "/authorization/{$code}");
});


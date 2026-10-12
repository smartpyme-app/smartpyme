<?php

use App\Http\Controllers\Api\SuperAdmin\ContadorAccesosController;
use Illuminate\Support\Facades\Route;

Route::middleware('superadmin')->group(function () {
    Route::get('/superadmin/contador-accesos', [ContadorAccesosController::class, 'index']);
    Route::get('/superadmin/contador-portal-usuarios', [ContadorAccesosController::class, 'contadoresPortal']);
    Route::post('/superadmin/contador-portal-usuario', [ContadorAccesosController::class, 'storeContador']);
    Route::post('/superadmin/contador-portal-usuario/incluir', [ContadorAccesosController::class, 'incluirContadorExistente']);
    Route::post('/superadmin/contador-acceso', [ContadorAccesosController::class, 'store']);
    Route::delete('/superadmin/contador-acceso/{id}', [ContadorAccesosController::class, 'destroy']);
});

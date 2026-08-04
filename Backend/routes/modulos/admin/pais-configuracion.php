<?php

use App\Http\Controllers\Api\Admin\PaisConfiguracionController;
use Illuminate\Support\Facades\Route;

Route::middleware('role:super_admin')->group(function () {
    Route::get('/pais-configuraciones', [PaisConfiguracionController::class, 'index']);
    Route::get('/pais-configuracion/{id}', [PaisConfiguracionController::class, 'read']);
    Route::post('/pais-configuracion', [PaisConfiguracionController::class, 'store']);
    Route::put('/pais-configuracion/{id}', [PaisConfiguracionController::class, 'update']);
    Route::delete('/pais-configuracion/{id}', [PaisConfiguracionController::class, 'delete']);
});

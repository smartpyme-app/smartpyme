<?php

use App\Http\Controllers\Cliente360\Cliente360Controller;
use Illuminate\Support\Facades\Route;

// Rutas para Lealtad de Clientes
Route::prefix('cliente-360')->group(function () {
    
    // Listar clientes con paginación y búsqueda
    Route::get('/', [Cliente360Controller::class, 'index']);

    // Cliente con más puntos disponibles (empresa del usuario)
    Route::get('/top-puntos', [Cliente360Controller::class, 'topPuntos']);
    
    // Obtener datos completos de un cliente específico
    Route::get('/{id}', [Cliente360Controller::class, 'show']);
    
    // Obtener solo métricas básicas (endpoint ligero)
    Route::get('/{id}/metrics', [Cliente360Controller::class, 'metrics']);

});

<?php

use App\Http\Controllers\Api\Ventas\VentaProgramadaController;
use Illuminate\Support\Facades\Route;

    Route::get('/ventas-programadas', [VentaProgramadaController::class, 'index']);
    Route::post('/ventas-programadas', [VentaProgramadaController::class, 'store']);
    Route::get('/ventas-programadas/{id}', [VentaProgramadaController::class, 'show']);
    Route::put('/ventas-programadas/{id}', [VentaProgramadaController::class, 'update']);
    Route::patch('/ventas-programadas/{id}/activo', [VentaProgramadaController::class, 'activo']);
    Route::get('/ventas-programadas/{id}/ejecuciones', [VentaProgramadaController::class, 'ejecuciones']);
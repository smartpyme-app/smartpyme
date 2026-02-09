<?php

use App\Http\Controllers\Api\Inventario\LotesController;
use Illuminate\Support\Facades\Route;

Route::prefix('lotes')->group(function () {
    Route::get('/', [LotesController::class, 'index']);
    Route::get('/estadisticas', [LotesController::class, 'getEstadisticas']);
    Route::get('/disponibles', [LotesController::class, 'getDisponibles']);
    Route::get('/proximos-vencer', [LotesController::class, 'getProximosAVencer']);
    Route::get('/vencidos', [LotesController::class, 'getVencidos']);
    Route::get('/producto/{productoId}', [LotesController::class, 'getByProducto']);
    Route::get('/{id}', [LotesController::class, 'show']);
    Route::post('/', [LotesController::class, 'store']);
    Route::put('/{id}', [LotesController::class, 'update']);
    Route::delete('/{id}', [LotesController::class, 'destroy']);
});

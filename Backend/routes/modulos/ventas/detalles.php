<?php 

use App\Http\Controllers\Api\Ventas\DetallesController;
use Illuminate\Support\Facades\Route;

    Route::get('/venta/detalles',           [DetallesController::class, 'index']);
    Route::get('/venta/detalle/{id}',       [DetallesController::class, 'read']);
    Route::post('/venta/detalle',           [DetallesController::class, 'store']);
    Route::delete('/venta/detalle/{id}',    [DetallesController::class, 'delete']);
    Route::post('/ventas/detalle',          [DetallesController::class, 'historial']);
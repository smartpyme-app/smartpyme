<?php 

use App\Http\Controllers\Api\Ventas\AbonosController;

    Route::get('/ventas/abonos',           [AbonosController::class, 'index']);
    Route::get('/venta/abono/{id}',       [AbonosController::class, 'read']);
    Route::post('/venta/abono',           [AbonosController::class, 'store']);
    Route::delete('/venta/abono/{id}',    [AbonosController::class, 'delete']);

    Route::get('/venta/abono/imprimir/{id}',    [AbonosController::class, 'print']);

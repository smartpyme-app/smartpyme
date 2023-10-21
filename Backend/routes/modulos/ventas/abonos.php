<?php 

use App\Http\Controllers\Api\Ventas\AbonosController;

    Route::get('/abonos',           [AbonosController::class, 'index']);
    Route::get('/abono/{id}',       [AbonosController::class, 'read']);
    Route::post('/abono',           [AbonosController::class, 'store']);
    Route::delete('/abono/{id}',    [AbonosController::class, 'delete']);

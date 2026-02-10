<?php 

use App\Http\Controllers\Api\Compras\Gastos\AbonosController;

    Route::get('/gastos/abonos',           [AbonosController::class, 'index']);
    Route::get('/gasto/abono/{id}',       [AbonosController::class, 'read']);
    Route::post('/gasto/abono',           [AbonosController::class, 'store']);
    Route::delete('/gasto/abono/{id}',    [AbonosController::class, 'delete']);
    Route::get('/gastos/abonos/exportar',    [AbonosController::class, 'export']);



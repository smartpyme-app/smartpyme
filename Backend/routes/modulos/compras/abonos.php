<?php 

use App\Http\Controllers\Api\Compras\AbonosController;

    Route::get('/compras/abonos',           [AbonosController::class, 'index']);
    Route::get('/compra/abono/{id}',       [AbonosController::class, 'read']);
    Route::post('/compra/abono',           [AbonosController::class, 'store']);
    Route::delete('/compra/abono/{id}',    [AbonosController::class, 'delete']);
    Route::get('/compras/abonos/exportar',    [AbonosController::class, 'export']);
    Route::get('/compra/abono/imprimir/{id}',    [AbonosController::class, 'print']);

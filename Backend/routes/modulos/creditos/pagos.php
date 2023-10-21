<?php

    use App\Http\Controllers\Api\Creditos\PagosController;

    Route::get('/pagos',                  [PagosController::class, 'index']);
    Route::get('/credito/pago/{id}',      [PagosController::class, 'read']);
    Route::post('/credito/pago',          [PagosController::class, 'store']);
    Route::delete('/credito/pago/{id}',   [PagosController::class, 'delete']);

?>
<?php 

use App\Http\Controllers\Api\SuperAdmin\PagosController;

    Route::get('/pagos',                 [PagosController::class, 'index']);
    Route::post('/pago',                 [PagosController::class, 'store']);
    Route::get('/pago/{id}',             [PagosController::class, 'read']);
    Route::delete('/pago/{id}',          [PagosController::class, 'delete']);

    Route::get('/pago/generar-venta/{id}',          [PagosController::class, 'generarVenta']);

?>

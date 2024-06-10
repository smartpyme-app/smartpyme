<?php

    use App\Http\Controllers\Api\Bancos\TransaccionesController;

    Route::get('/bancos/transacciones',             [TransaccionesController::class, 'index']);
    Route::get('/bancos/transaccion/{id}',          [TransaccionesController::class, 'read']);
    Route::get('/bancos/transacciones/list',        [TransaccionesController::class, 'list']);
    Route::post('/bancos/transaccion',              [TransaccionesController::class, 'store']);
    Route::delete('/bancos/transaccion/{id}',       [TransaccionesController::class, 'delete']);

?>

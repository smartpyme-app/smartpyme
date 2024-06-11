<?php

    use App\Http\Controllers\Api\Bancos\TransaccionesController;

    Route::get('/bancos/transacciones',            [TransaccionesController::class, 'index']);
    Route::get('/banco/transaccion/{id}',          [TransaccionesController::class, 'read']);
    Route::get('/banco/transacciones/list',        [TransaccionesController::class, 'list']);
    Route::post('/banco/transaccion',              [TransaccionesController::class, 'store']);
    Route::delete('/banco/transaccion/{id}',       [TransaccionesController::class, 'delete']);

?>

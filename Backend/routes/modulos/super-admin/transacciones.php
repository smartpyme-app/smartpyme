<?php 

use App\Http\Controllers\Api\SuperAdmin\TransaccionesController;

    Route::get('/transacciones',                 [TransaccionesController::class, 'index']);
    Route::get('/transaccion/{id}',              [TransaccionesController::class, 'read']);
    Route::post('/transaccion',                  [TransaccionesController::class, 'store']);
    Route::delete('/transaccion/{id}',           [TransaccionesController::class, 'delete']);


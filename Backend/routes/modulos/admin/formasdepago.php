<?php 

use App\Http\Controllers\Api\Admin\FormasDePagosController;

    Route::get('/formas-de-pago',                  [FormasDePagosController::class, 'index']);
    Route::get('/forma-de-pago/{id}',              [FormasDePagosController::class, 'read']);
    Route::post('/forma-de-pago',                  [FormasDePagosController::class, 'store']);
    Route::delete('/forma-de-pago/{id}',              [FormasDePagosController::class, 'delete']);
    

?>

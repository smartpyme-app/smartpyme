<?php 

use App\Http\Controllers\Api\Admin\FormasDePagosController;

    Route::get('/formas-de-pago', [FormasDePagosController::class, 'index']);
    Route::get('/formas-de-pago/list', [FormasDePagosController::class, 'list']);
    Route::post('/forma-de-pago', [FormasDePagosController::class, 'storeOrDelete']);
    Route::delete('/forma-de-pago/{id}', [FormasDePagosController::class, 'delete']);

    Route::post('/wompi', [FormasDePagosController::class, 'wompi']);
    

?>

<?php 

use App\Http\Controllers\Api\Admin\FormasDePagosController;

    Route::get('/formas-de-pago', [FormasDePagosController::class, 'index']);
    Route::post('/forma-de-pago', [FormasDePagosController::class, 'storeOrDelete']);

    Route::post('/wompi', [FormasDePagosController::class, 'wompi']);
    

?>

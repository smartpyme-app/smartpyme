<?php 

use App\Http\Controllers\Api\Admin\CanalesController;

    Route::get('/canales',                  [CanalesController::class, 'index']);
    Route::get('/canal/{id}',              [CanalesController::class, 'read']);
    Route::post('/canal',                  [CanalesController::class, 'store']);
    Route::delete('/canal/{id}',              [CanalesController::class, 'delete']);
    

?>

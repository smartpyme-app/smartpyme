<?php 

use App\Http\Controllers\Api\Admin\BancosController;

    Route::get('/bancos',                  [BancosController::class, 'index']);
    Route::get('/banco/{id}',              [BancosController::class, 'read']);
    Route::post('/banco',                  [BancosController::class, 'store']);
    Route::delete('/banco/{id}',              [BancosController::class, 'delete']);
    

?>

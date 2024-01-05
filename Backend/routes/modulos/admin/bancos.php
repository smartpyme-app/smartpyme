<?php 

use App\Http\Controllers\Api\Admin\BancosController;

    Route::get('/bancos', [BancosController::class, 'index']);
    Route::get('/bancos/list', [BancosController::class, 'list']);
    Route::post('/banco', [BancosController::class, 'storeOrDelete']);

?>

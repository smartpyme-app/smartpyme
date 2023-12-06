<?php 

use App\Http\Controllers\Api\Admin\BancosController;

    Route::get('/bancos', [BancosController::class, 'index']);
    Route::post('/banco', [BancosController::class, 'storeOrDelete']);

?>

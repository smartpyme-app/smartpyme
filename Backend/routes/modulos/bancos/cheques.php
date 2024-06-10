<?php

    use App\Http\Controllers\Api\Bancos\ChequesController;

    Route::get('/bancos/cheques',              [ChequesController::class, 'index']);
    Route::get('/bancos/cheque/{id}',          [ChequesController::class, 'read']);
    Route::get('/bancos/cheques/list',         [ChequesController::class, 'list']);
    Route::post('/bancos/cheque',              [ChequesController::class, 'store']);
    Route::delete('/bancos/cheque/{id}',       [ChequesController::class, 'delete']);

?>

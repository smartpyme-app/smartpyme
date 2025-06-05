<?php

    use App\Http\Controllers\Api\Bancos\ChequesController;

    Route::get('/bancos/cheques',              [ChequesController::class, 'index']);
    Route::get('/banco/cheque/{id}',          [ChequesController::class, 'read']);
    Route::get('/banco/cheques/list',         [ChequesController::class, 'list']);
    Route::post('/banco/cheque',              [ChequesController::class, 'store']);
    Route::delete('/banco/cheque/{id}',       [ChequesController::class, 'delete']);

    Route::get('/banco/cheque/imprimir/{id}',       [ChequesController::class, 'generarDoc']);

    Route::get('/bancos/cheques/exportar',    [ChequesController::class, 'export']);

?>

<?php 

use App\Http\Controllers\Api\Admin\ImpuestosController;

    Route::get('/impuestos',                  [ImpuestosController::class, 'index']);
    Route::get('/impuesto/{id}',              [ImpuestosController::class, 'read']);
    Route::post('/impuesto',                  [ImpuestosController::class, 'store']);
    Route::delete('/impuesto/{id}',              [ImpuestosController::class, 'delete']);
    


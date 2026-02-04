<?php 

use App\Http\Controllers\Api\Inventario\AjustesController;

    Route::get('/ajustes',         		[AjustesController::class, 'index']);
    Route::post('/ajuste',         		[AjustesController::class, 'store']);
    Route::get('/ajuste/{id}',     		[AjustesController::class, 'read']);
    Route::delete('/ajuste/{id}',  		[AjustesController::class, 'delete']);
    Route::get('/ajustes/exportar',          [AjustesController::class, 'export']);


<?php 

use App\Http\Controllers\Api\Inventario\AjustesController;

    Route::get('/ajustes',         		[AjustesController::class, 'index']);
    Route::get('/ajustes/buscar/{text}',[AjustesController::class, 'search']);
    Route::post('/ajuste',         		[AjustesController::class, 'store']);
    Route::post('/ajustes/filtrar',     [AjustesController::class, 'filter']);
    Route::get('/ajuste/{id}',     		[AjustesController::class, 'read']);
    Route::delete('/ajuste/{id}',  		[AjustesController::class, 'delete']);


?>
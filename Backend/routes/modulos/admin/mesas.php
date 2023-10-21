<?php 

use App\Http\Controllers\Api\Admin\MesasController;

    Route::get('/mesas',                 [MesasController::class, 'index']);
    Route::post('/mesa',                 [MesasController::class, 'store']);
    Route::post('/mesas/filtrar',         [MesasController::class, 'filter']);
    Route::get('/mesa/{id}',             [MesasController::class, 'read']);
    Route::delete('/mesa/{id}',          [MesasController::class, 'delete']);

?>
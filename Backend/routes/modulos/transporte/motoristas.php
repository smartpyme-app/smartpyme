<?php 

use App\Http\Controllers\Api\Transporte\Motoristas\MotoristasController;

    Route::get('/motoristas',                    [MotoristasController::class, 'index']);
    Route::get('/motoristas/list',               [MotoristasController::class, 'list']);
    Route::get('/motoristas/buscar/{text}',      [MotoristasController::class, 'search']);
    Route::get('/motorista/{id}',                [MotoristasController::class, 'read']);
    Route::post('/motoristas/filtrar',           [MotoristasController::class, 'filter']);
    Route::post('/motorista',                    [MotoristasController::class, 'store']);
    Route::delete('/motorista/{id}',             [MotoristasController::class, 'delete']);
    Route::get('/motorista/impresion/{id}',      [MotoristasController::class, 'generarDoc']);

    Route::post('/motoristas/fletes',         [MotoristasController::class, 'fletes']);


?>

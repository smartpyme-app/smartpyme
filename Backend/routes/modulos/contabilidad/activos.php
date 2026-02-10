<?php 

use App\Http\Controllers\Api\Contabilidad\Activos\ActivosController;
use App\Http\Controllers\Api\Contabilidad\Activos\CategoriasController;

    Route::get('/activos',             [ActivosController::class, 'index']);
    Route::post('/activo',             [ActivosController::class, 'store']);
    Route::get('/activo/{id}',         [ActivosController::class, 'read']);
    Route::post('/activos/filtrar',    [ActivosController::class, 'filter']);
    Route::delete('/activo/{id}',      [ActivosController::class, 'delete']);

    Route::get('/activos/categorias',             [CategoriasController::class, 'index']);
    Route::post('/activos/categoria',             [CategoriasController::class, 'store']);
    Route::delete('/activos/categoria/{id}',         [CategoriasController::class, 'delete']);


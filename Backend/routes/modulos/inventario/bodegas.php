<?php 

use App\Http\Controllers\Api\Inventario\BodegasController;

    Route::get('/bodegas',                      [BodegasController::class, 'index']);
    Route::get('/bodega/{id}',                  [BodegasController::class, 'read']);
    Route::get('/bodegas/list',                 [BodegasController::class, 'list']);
    Route::post('/bodega',                      [BodegasController::class, 'store']);
    Route::get('/reporte/bodegas/{id}/{cat}',   [BodegasController::class, 'reporte']);
    Route::delete('/bodega/{id}',               [BodegasController::class, 'delete']);

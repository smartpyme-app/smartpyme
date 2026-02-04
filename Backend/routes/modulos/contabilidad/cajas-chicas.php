<?php 

use App\Http\Controllers\Api\Contabilidad\CajaChica\CajasChicasController;
use App\Http\Controllers\Api\Contabilidad\CajaChica\DetallesController;

    Route::get('/cajas-chicas',             [CajasChicasController::class, 'index']);
    Route::post('/caja-chica',             [CajasChicasController::class, 'store']);
    Route::get('/caja-chica/{id}',         [CajasChicasController::class, 'read']);
    Route::post('/cajas-chicas/filtrar',    [CajasChicasController::class, 'filter']);
    Route::delete('/caja-chica/{id}',      [CajasChicasController::class, 'delete']);
    Route::get('/caja-chica/reporte/{id}',      [CajasChicasController::class, 'reporte']);

    Route::post('/caja-chica/detalles/filtrar',    [DetallesController::class, 'filter']);
    Route::post('/caja-chica/detalle',             [DetallesController::class, 'store']);
    Route::get('/caja-chica/detalle/{id}',         [DetallesController::class, 'read']);
    Route::delete('/caja-chica/detalle/{id}',      [DetallesController::class, 'delete']);



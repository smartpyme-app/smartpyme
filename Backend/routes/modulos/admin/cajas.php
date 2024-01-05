<?php

use App\Http\Controllers\Api\Admin\CajasController;
use App\Http\Controllers\Api\Admin\CortesController;
use App\Http\Controllers\Api\Admin\DocumentosController;

// Cajas
	Route::get('/cajas',               [CajasController::class, 'index']);
	Route::get('/caja/{id}',           [CajasController::class, 'read']);
	Route::post('/caja',               [CajasController::class, 'store']);
	Route::get('/caja/cortes/{id}',    [CajasController::class, 'cortes']);

    Route::post('/caja/estadisticas',   [CajasController::class, 'estadisticas']);

    Route::get('/caja/reporte-dia/{id}',   [CajasController::class, 'reporteDia']);

// Cortes

    Route::post('/corte',                 [CortesController::class, 'store']);
	Route::get('/corte/{id}',             [CortesController::class, 'read']);
    Route::post('/cortes/filtrar',        [CortesController::class, 'filter']);
	Route::get('/corte/reporte/{id}',     [CortesController::class, 'reporte']);
    Route::get('/corte/ventas/{id}',     [CortesController::class, 'ventas']);
    Route::get('/corte/devoluciones-ventas/{id}',     [CortesController::class, 'devoluciones']);

// Documentos
    Route::get('/documentos',             [DocumentosController::class, 'index']);
    Route::get('/documentos/list',         [DocumentosController::class, 'list']);
    Route::get('/documento/{id}',         [DocumentosController::class, 'read']);
    Route::post('/documento',             [DocumentosController::class, 'store']);
    Route::delete('/documento/{id}',       [DocumentosController::class, 'delete']);

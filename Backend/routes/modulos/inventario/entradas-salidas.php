<?php 

use App\Http\Controllers\Api\Inventario\Entradas\EntradasController;
use App\Http\Controllers\Api\Inventario\Entradas\DetallesController as EDController;

use App\Http\Controllers\Api\Inventario\Salidas\SalidasController;
use App\Http\Controllers\Api\Inventario\Salidas\DetallesController as SDController;

// Entradas
    Route::get('/entradas',                [EntradasController::class, 'index']);
    Route::get('/entrada/{id}',            [EntradasController::class, 'read']);
    Route::get('/entradas/buscar/{txt}',   [EntradasController::class, 'search']);
    Route::post('/entradas/filtrar',       [EntradasController::class, 'filter']);
    Route::post('/entrada',                [EntradasController::class, 'store']);
    Route::delete('/entrada/{id}',         [EntradasController::class, 'delete']);
    Route::post('/entrada/aprobar/{id}',   [EntradasController::class, 'aprobar']);
    Route::post('/entrada/anular/{id}',    [EntradasController::class, 'anular']);
    Route::get('/reporte/entrada/{id}',    [EntradasController::class, 'generarDoc']);

// Detalles
    Route::get('/entrada/detalles',        [EDController::class, 'index']);
    Route::get('/entrada/detalle/{id}',    [EDController::class, 'read']);
    Route::post('/entrada/detalle',        [EDController::class, 'store']);
    Route::delete('/entrada/detalle/{id}', [EDController::class, 'delete']);

// Salidas
    Route::get('/salidas',                [SalidasController::class, 'index']);
    Route::get('/salida/{id}',            [SalidasController::class, 'read']);
    Route::get('/salidas/buscar/{txt}',   [SalidasController::class, 'search']);
    Route::post('/salidas/filtrar',       [SalidasController::class, 'filter']);
    Route::post('/salida',                [SalidasController::class, 'store']);
    Route::delete('/salida/{id}',         [SalidasController::class, 'delete']);
    Route::post('/salida/aprobar/{id}',   [SalidasController::class, 'aprobar']);
    Route::post('/salida/anular/{id}',    [SalidasController::class, 'anular']);
    Route::get('/reporte/salida/{id}',    [SalidasController::class, 'generarDoc']);

// Detalles
    Route::get('/salida/detalles',        [SDController::class, 'index']);
    Route::get('/salida/detalle/{id}',    [SDController::class, 'read']);
    Route::post('/salida/detalle',        [SDController::class, 'store']);
    Route::delete('/salida/detalle/{id}', [SDController::class, 'delete']);



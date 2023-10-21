<?php 

use App\Http\Controllers\Api\Transporte\Mantenimientos\MantenimientosController;
use App\Http\Controllers\Api\Transporte\Mantenimientos\DetallesController;
use App\Http\Controllers\Api\Transporte\Mantenimientos\RepuestosController;

    Route::get('/mantenimientos',                  [MantenimientosController::class, 'index']);
    Route::get('/mantenimientos/buscar/{txt}',     [MantenimientosController::class, 'search']);
    Route::post('/mantenimientos/filtrar',         [MantenimientosController::class, 'filter']);
    Route::get('/mantenimiento/{id}',              [MantenimientosController::class, 'read']);
    Route::post('/mantenimiento',                  [MantenimientosController::class, 'store']);
    Route::delete('/mantenimiento/{id}',           [MantenimientosController::class, 'delete']);
    Route::post('/mantenimiento/facturacion',      [MantenimientosController::class, 'facturacion']);
    Route::post('/mantenimientos/historial',       [MantenimientosController::class, 'historial']);
    Route::get('/mantenimiento/imprimir/{id}',    [MantenimientosController::class, 'generarDoc']);


    Route::get('/mantenimiento/detalle/{id}',     [DetallesController::class, 'read']);
    Route::post('/mantenimiento/detalle',         [DetallesController::class, 'store']);
    Route::delete('/mantenimiento/detalle/{id}',  [DetallesController::class, 'delete']);
    Route::post('/mantenimientos/detalle',          [DetallesController::class, 'historial']);

    Route::get('/flota/mantenimientos/{id}', [MantenimientosController::class, 'flota']);

    Route::get('/repuestos',                    [RepuestosController::class, 'index']);
    Route::get('/repuesto/{id}',                [RepuestosController::class, 'read']);
    Route::get('/repuestos/list',               [RepuestosController::class, 'list']);
    Route::get('/repuestos/buscar/{text}',      [RepuestosController::class, 'search']);
    Route::get('/repuestos-all/buscar/{text}',  [RepuestosController::class, 'searchAll']);
    Route::post('/repuestos/filtrar',           [RepuestosController::class, 'filter']);
    Route::post('/repuesto',                    [RepuestosController::class, 'store']);
    Route::delete('/repuesto/{id}',             [RepuestosController::class, 'delete']);

?>

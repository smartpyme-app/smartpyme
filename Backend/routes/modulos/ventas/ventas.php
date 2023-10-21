<?php 

use App\Http\Controllers\Api\Ventas\VentasController;
use App\Http\Controllers\Api\Ventas\EntradasController;

    Route::get('/ventas',               [VentasController::class, 'index']);
    Route::get('/ventas/buscar/{txt}',  [VentasController::class, 'search']);
    Route::post('/ventas/filtrar',      [VentasController::class, 'filter']);
    Route::get('/venta/{id}',           [VentasController::class, 'read']);
    Route::post('/venta',               [VentasController::class, 'store']);
    Route::delete('/venta/{id}',        [VentasController::class, 'delete']);

    Route::post('/venta/facturacion',  [VentasController::class, 'facturacion']);
    Route::get('/venta/facturacion/impresion/{id}',  [VentasController::class, 'generarDoc']);

    Route::get('/ventas/pendientes',       [VentasController::class, 'pendientes']);

    Route::post('/propinas',             [VentasController::class, 'propinas']);

    Route::post('/libro-iva',           [VentasController::class, 'libroIva']);
    Route::post('/galonaje',            [VentasController::class, 'galonaje']);

    Route::post('/ventas/historial',    [VentasController::class, 'historial']);

?>

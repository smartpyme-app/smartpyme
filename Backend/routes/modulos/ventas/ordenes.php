<?php 

use App\Http\Controllers\Api\Ventas\Ordenes\OrdenesController;
use App\Http\Controllers\Api\Ventas\Ordenes\DetallesController;

    Route::get('/ordenes',                    [OrdenesController::class, 'index']);
    Route::get('/ordenes/buscar/{text}',      [OrdenesController::class, 'search']);
    Route::get('/orden/{id}',                [OrdenesController::class, 'read']);
    Route::post('/ordenes/filtrar',           [OrdenesController::class, 'filter']);
    Route::post('/orden',                    [OrdenesController::class, 'store']);
    Route::delete('/orden/{id}',             [OrdenesController::class, 'delete']);
    Route::post('/orden/facturacion',        [OrdenesController::class, 'facturacion']);
    Route::get('/orden/impresion/{id}',        [OrdenesController::class, 'generarDoc']);

    Route::get('/orden/detalles',           [DetallesController::class, 'index']);
    Route::get('/orden/detalle/{id}',       [DetallesController::class, 'read']);
    Route::post('/orden/detalle',           [DetallesController::class, 'store']);
    Route::delete('/orden/detalle/{id}',    [DetallesController::class, 'delete']);
    Route::post('/ordens/detalle',          [DetallesController::class, 'historial']);


?>

<?php 

use App\Http\Controllers\Api\Ordenes\OrdenesController;

    Route::get('/ordenes',                    [OrdenesController::class, 'index']);
    Route::get('/ordenes/buscar/{text}',      [OrdenesController::class, 'search']);
    Route::get('/orden/{id}',                [OrdenesController::class, 'read']);
    Route::post('/ordenes/filtrar',           [OrdenesController::class, 'filter']);
    Route::post('/orden',                    [OrdenesController::class, 'store']);
    Route::delete('/orden/{id}',             [OrdenesController::class, 'delete']);


    Route::post('/orden/facturacion',        [OrdenesController::class, 'facturacion']);
    Route::get('/orden/impresion/{id}',        [OrdenesController::class, 'generarDoc']);


?>

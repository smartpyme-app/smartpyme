<?php 

use App\Http\Controllers\Api\Transporte\Flotas\FlotasController;

    Route::get('/flotas',                    [FlotasController::class, 'index']);
    Route::get('/flotas/buscar/{text}',      [FlotasController::class, 'search']);
    Route::get('/flota/{id}',                [FlotasController::class, 'read']);
    Route::post('/flotas/filtrar',           [FlotasController::class, 'filter']);
    Route::post('/flota',                    [FlotasController::class, 'store']);
    Route::delete('/flota/{id}',             [FlotasController::class, 'delete']);
    Route::get('/flota/impresion/{id}',        [FlotasController::class, 'generarDoc']);

    Route::post('/flota/datos',              [FlotasController::class, 'dash']);


?>

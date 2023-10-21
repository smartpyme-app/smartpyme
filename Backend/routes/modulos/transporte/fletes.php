<?php 

use App\Http\Controllers\Api\Transporte\Fletes\FletesController;
use App\Http\Controllers\Api\Transporte\Fletes\DetallesController;

    Route::get('/fletes',                    [FletesController::class, 'index']);
    Route::get('/fletes/buscar/{text}',      [FletesController::class, 'search']);
    Route::get('/flete/{id}',                [FletesController::class, 'read']);
    Route::post('/fletes/filtrar',           [FletesController::class, 'filter']);
    Route::post('/flete',                    [FletesController::class, 'store']);
    Route::delete('/flete/{id}',             [FletesController::class, 'delete']);
    Route::get('/flete/impresion/{id}',      [FletesController::class, 'generarDoc']);
    Route::get('/fletes/pendientes',      [FletesController::class, 'pendientes']);

    Route::get('/flete/orden-de-carga/{id}', [FletesController::class, 'ordenDeCarga']);
    Route::get('/flete/carta-de-porte/{id}', [FletesController::class, 'cartaDePorte']);
    Route::get('/flete/manifiesto-de-carga/{id}', [FletesController::class, 'manifiestoDeCarga']);

    Route::post('/flete/detalle',         [DetallesController::class, 'store']);
    Route::delete('/flete/detalle/{id}',  [DetallesController::class, 'delete']);

    Route::get('/flota/fletes/{id}', [FletesController::class, 'flota']);

    Route::get('/empleado/fletes/{id}', [FletesController::class, 'empleado']);
    Route::get('/cliente/fletes/{id}', [FletesController::class, 'cliente']);

?>

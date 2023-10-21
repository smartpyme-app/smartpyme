<?php

    use App\Http\Controllers\Api\Eventos\EventosController;

    Route::get('/eventos',                    [EventosController::class, 'index']);
    Route::get('/evento/{id}',                [EventosController::class, 'read']);
    Route::get('/eventos/buscar/{text}',      [EventosController::class, 'search']);
    Route::post('/eventos/filtrar',           [EventosController::class, 'filter']);
    Route::post('/evento',                    [EventosController::class, 'store']);
    Route::delete('/evento/{id}',             [EventosController::class, 'delete']);

    Route::post('/evento/facturacion',        [EventosController::class, 'facturacion']);

?>
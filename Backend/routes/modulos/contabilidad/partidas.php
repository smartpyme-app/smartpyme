<?php 

use App\Http\Controllers\Api\Contabilidad\partidas\PartidasController;
use App\Http\Controllers\Api\Contabilidad\partidas\DetallesController;

    Route::get('/partidas',             [PartidasController::class, 'index']);
    Route::post('/partida',             [PartidasController::class, 'store']);
    Route::get('/partida/{id}',         [PartidasController::class, 'read']);
    Route::delete('/partida/{id}',      [PartidasController::class, 'delete']);

    Route::get('/partida/detalles',             [DetallesController::class, 'index']);
    Route::post('/partida/detalle',             [DetallesController::class, 'store']);
    Route::get('/partida/detalle/{id}',         [DetallesController::class, 'read']);
    Route::delete('/partida/detalle/{id}',      [DetallesController::class, 'delete']);

?>

<?php

use App\Http\Controllers\Api\Contabilidad\Partidas\PartidasController;
use App\Http\Controllers\Api\Contabilidad\Partidas\DetallesController;
use App\Http\Controllers\Api\Ventas\GenerarDocumentosController; //aplicado como prueba para obtener de la base de datos y mostrar el resultado

    Route::get('/partidas',             [PartidasController::class, 'index']);
    Route::post('/partida',             [PartidasController::class, 'store']);
    Route::get('/partida/{id}',         [PartidasController::class, 'read']);
    Route::delete('/partida/{id}',      [PartidasController::class, 'delete']);

    Route::get('/partidas/detalles',             [GenerarDocumentosController::class, 'generarRepLibroDiarioAux']); //genera el libro diario auxiliar solamente como temporal
    Route::post('/partida/detalle',             [DetallesController::class, 'store']);
    Route::get('/partida/detalle/{id}',         [DetallesController::class, 'read']);
    Route::delete('/partida/detalle/{id}',      [DetallesController::class, 'delete']);

    Route::get('/partidas/diario/mayor',        [GenerarDocumentosController::class, 'generarRepLibroDiarioMayor']); //genera el libro diario mayor solamente como temporal

?>

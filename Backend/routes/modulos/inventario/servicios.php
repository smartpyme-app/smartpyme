<?php 

use App\Http\Controllers\Api\Inventario\ServiciosController;
use App\Http\Controllers\Api\Inventario\KardexController;

    Route::get('/servicios',                    [ServiciosController::class, 'index']);
    Route::get('/servicio/{id}',                [ServiciosController::class, 'read']);
    Route::get('/servicios/list',                [ServiciosController::class, 'list']);
    Route::get('/servicios/buscar/{text}',      [ServiciosController::class, 'search']);
    Route::get('/servicios-all/buscar/{text}',  [ServiciosController::class, 'searchAll']);
    Route::post('/servicios/filtrar',           [ServiciosController::class, 'filter']);
    Route::post('/servicio',                    [ServiciosController::class, 'store']);
    Route::delete('/servicio/{id}',             [ServiciosController::class, 'delete']);

    Route::get('/servicios/buscar-codigo/{codigo}', [ServiciosController::class, 'porCodigo']);

    Route::post('/servicio/kardex',             [KardexController::class, 'index']);

    Route::post('/servicios/analisis',          [ServiciosController::class, 'analisis']);
    Route::get('/servicio/precios/historicos/{id}', [ServiciosController::class, 'precios']);


?>

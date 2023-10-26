<?php 

// use App\Http\Controllers\Api\Inventario\Traslados\TrasladosController;
// use App\Http\Controllers\Api\Inventario\Traslados\DetallesController;

    // Route::get('/traslados',         		[TrasladosController::class, 'index']);
    // Route::get('/traslado/{id}',     		[TrasladosController::class, 'read']);
    // Route::get('/traslados/buscar/{txt}',   [TrasladosController::class, 'search']);
    // Route::post('/traslados/filtrar',  	    [TrasladosController::class, 'filter']);
    // Route::post('/traslado',         		[TrasladosController::class, 'store']);
    // Route::delete('/traslado/{id}',          [TrasladosController::class, 'delete']);

    // Route::get('/traslados/requisicion/{origen_id}/{destino_id}',          [TrasladosController::class, 'requisicion']);
    // Route::post('/traslados/bodega/filtrar', [TrasladosController::class, 'bodegaFiltrar']);

// Detalles
    // Route::get('/traslado/detalles',  		[DetallesController::class, 'index']);
    // Route::get('/traslado/detalle/{id}',  	[DetallesController::class, 'read']);
    // Route::post('/traslado/detalle',  		[DetallesController::class, 'store']);
    // Route::delete('/traslado/detalle/{id}', [DetallesController::class, 'delete']);

    // Route::get('/reporte/traslado/{id}',    [TrasladosController::class, 'generarDoc']);

use App\Http\Controllers\Api\Inventario\TrasladosController;

Route::get('/traslados',                 [TrasladosController::class, 'index']);
Route::get('/traslado/{id}',             [TrasladosController::class, 'read']);
Route::get('/traslados/buscar/{txt}',   [TrasladosController::class, 'search']);
Route::post('/traslados/filtrar',        [TrasladosController::class, 'filter']);
Route::post('/traslado',                 [TrasladosController::class, 'store']);
Route::delete('/traslado/{id}',          [TrasladosController::class, 'delete']);

?>

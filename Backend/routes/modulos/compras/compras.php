<?php 

use App\Http\Controllers\Api\Compras\ComprasController;
use App\Http\Controllers\Api\Compras\SalidasController;

    Route::get('/compras',         		    [ComprasController::class, 'index']);
    Route::get('/compras/buscar/{txt}',     [ComprasController::class, 'search']);
    Route::post('/compras/filtrar',			[ComprasController::class, 'filter']);
    Route::get('/compra/{id}',              [ComprasController::class, 'read']);
    Route::post('/compra',                  [ComprasController::class, 'store']);
    Route::delete('/compra/{id}',           [ComprasController::class, 'delete']);
    
    Route::post('/compra/facturacion',      [ComprasController::class, 'facturacion']);

    Route::post('/libro-compras',           [ComprasController::class, 'libroCompras']);

    Route::post('/compras/historial',       [ComprasController::class, 'historial']);

?>
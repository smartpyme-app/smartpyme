<?php

use App\Http\Controllers\Api\Compras\Devoluciones\DevolucionComprasController;
use App\Http\Controllers\Api\Compras\Devoluciones\DevolucionDetallesController;


    Route::get('/devoluciones/compras',             [DevolucionComprasController::class, 'index']);
    Route::post('/devolucion/compra',               [DevolucionComprasController::class, 'store']);
    Route::post('/devoluciones/compras/filtrar',      [DevolucionComprasController::class, 'filter']);
    Route::get('/devolucion/compra/{id}',           [DevolucionComprasController::class, 'read']);
    Route::delete('/devolucion/compra/{id}',        [DevolucionComprasController::class, 'delete']);
    
    Route::post('/devolucion-compra',               [DevolucionComprasController::class, 'facturacion']);
    Route::get('/devoluciones/compras/exportar',    [DevolucionComprasController::class, 'export']);
    
    Route::get('/devolucion/compra/detalle/{id}',          [DevolucionDetallesController::class, 'index']);
    Route::post('/devolucion/compra/detalle',              [DevolucionDetallesController::class, 'store']);
    Route::delete('/devolucion/compra/detalle/{id}',       [DevolucionDetallesController::class, 'delete']);

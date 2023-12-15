<?php 

use App\Http\Controllers\Api\Compras\Cotizaciones\CotizacionesController;
use App\Http\Controllers\Api\Compras\Cotizaciones\DetallesController;

    Route::get('/ordenes-de-compras',                    [CotizacionesController::class, 'index']);
    Route::get('/orden-de-compra/{id}',                [CotizacionesController::class, 'read']);
    Route::post('/orden-de-compra',                    [CotizacionesController::class, 'store']);
    Route::delete('/orden-de-compra/{id}',             [CotizacionesController::class, 'delete']);
    Route::post('/orden-de-compra/facturacion',        [CotizacionesController::class, 'facturacion']);
    Route::get('/orden-de-compra/impresion/{id}',        [CotizacionesController::class, 'generarDoc']);

    Route::get('/orden-de-compra/detalles',           [DetallesController::class, 'index']);
    Route::get('/orden-de-compra/detalle/{id}',       [DetallesController::class, 'read']);
    Route::post('/orden-de-compra/detalle',           [DetallesController::class, 'store']);
    Route::delete('/orden-de-compra/detalle/{id}',    [DetallesController::class, 'delete']);
    Route::post('/orden-de-compra/detalle',          [DetallesController::class, 'historial']);


?>

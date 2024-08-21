<?php 

use App\Http\Controllers\Api\Ventas\Devoluciones\DevolucionVentasController;
use App\Http\Controllers\Api\Ventas\Devoluciones\DevolucionDetallesController;

    // Devoluciones

    Route::get('/devoluciones/ventas',                 [DevolucionVentasController::class, 'index']);
    Route::get('/devoluciones/ventas/corte',           [DevolucionVentasController::class, 'corte']);
    Route::post('/devoluciones/ventas/filtrar',        [DevolucionVentasController::class, 'filter']);
    Route::post('/devolucion/venta',                   [DevolucionVentasController::class, 'store']);
    Route::post('/devolucion/venta/facturacion',             [DevolucionVentasController::class, 'facturacion']);
    Route::get('/devolucion/venta/{id}',               [DevolucionVentasController::class, 'read']);
    Route::delete('/devolucion/venta/{id}',            [DevolucionVentasController::class, 'delete']);
    Route::get('/devoluciones/ventas/exportar',    [DevolucionVentasController::class, 'export']);
    Route::get('/devolucion/facturacion/impresion/{id}',  [DevolucionVentasController::class, 'generarDoc']);

    Route::get('/devolucion/venta/detalle/{id}',       [DevolucionDetallesController::class, 'index']);
    Route::post('/devolucion/venta/detalle',           [DevolucionDetallesController::class, 'store']);
    Route::delete('/devolucion/venta/detalle/{id}',    [DevolucionDetallesController::class, 'delete']);

    Route::get('/devolucion/ventas/buscar/{txt}',      [DevolucionVentasController::class, 'search']);
    Route::get('/devolucion/ventas/{filtro}/{valor}',  [DevolucionVentasController::class, 'filtro']);
    Route::get('/devolucion/venta/detalles/{id}',      [DevolucionVentasController::class, 'detalles']);


?>

<?php

use App\Http\Controllers\Api\Compras\ComprasController;
use App\Http\Controllers\Api\Compras\SalidasController;
use Illuminate\Support\Facades\Route;

    Route::get('/compras',         		    [ComprasController::class, 'index']);
    Route::get('/compras/buscar/{txt}',     [ComprasController::class, 'search']);
    Route::post('/compras/filtrar',			[ComprasController::class, 'filter']);
    Route::get('/compra/{id}',              [ComprasController::class, 'read']);
    Route::post('/compra',                  [ComprasController::class, 'store']);
    Route::delete('/compra/{id}',           [ComprasController::class, 'delete']);

    Route::post('/compra/facturacion',      [ComprasController::class, 'facturacion']);
    Route::post('/compra/facturacion/consigna',  [ComprasController::class, 'facturacionConsigna']);

    Route::get('/compras/sin-devolucion',       [ComprasController::class, 'sinDevolucion']);

    Route::post('/compras/historial',       [ComprasController::class, 'historial']);

    Route::get('/compras/exportar',    [ComprasController::class, 'export']);
    Route::get('/compras-detalles/exportar',    [ComprasController::class, 'exportDetalles']);
    //compras-rentabilidad/exportar post
    Route::post('/compras-rentabilidad/exportar',    [ComprasController::class, 'exportRentabilidad']);

    Route::get('compras/nums-ids',    [ComprasController::class, 'getNumerosIdentificacion']);
    Route::post('marcar-recurrente',    [ComprasController::class, 'marcarRecurrente']);

?>

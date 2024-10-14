<?php

use App\Http\Controllers\Api\Ventas\Cotizaciones\CotizacionesController;
use App\Http\Controllers\Api\Ventas\Cotizaciones\DetallesController;
use App\Http\Controllers\CotizacionVentaController;
use Illuminate\Support\Facades\Route;

// Route::get('/cotizaciones',                    [CotizacionesController::class, 'index']);
Route::get('/cotizaciones/buscar/{text}',      [CotizacionesController::class, 'search']);
Route::get('/cotizacion/{id}',                [CotizacionesController::class, 'read']);
Route::post('/cotizaciones/filtrar',           [CotizacionesController::class, 'filter']);
Route::post('/cotizacion',                    [CotizacionesController::class, 'store']);
Route::delete('/cotizacion/{id}',             [CotizacionesController::class, 'delete']);
Route::post('/cotizacion/facturacion',        [CotizacionesController::class, 'facturacion']);
Route::get('/cotizacion/impresion/{id}',        [CotizacionesController::class, 'generarDoc']);

Route::get('/cotizaciones/exportar',    [CotizacionesController::class, 'export']);

Route::get('/cotizacion/detalles',           [DetallesController::class, 'index']);
Route::get('/cotizacion/detalle/{id}',       [DetallesController::class, 'read']);
Route::post('/cotizacion/detalle',           [DetallesController::class, 'store']);
Route::delete('/cotizacion/detalle/{id}',    [DetallesController::class, 'delete']);
Route::post('/cotizacion/detalle',          [DetallesController::class, 'historial']);

Route::get('/cotizaciones',                    [CotizacionVentaController::class, 'index']);

Route::post("cotizacionVentas", [CotizacionVentaController::class, 'store']);
Route::get("cotizacionVentas/{id}", [CotizacionVentaController::class, 'read']);

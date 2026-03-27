<?php

use App\Http\Controllers\Api\Admin\CostaRicaFeCatalogController;
use App\Http\Controllers\Api\Admin\CostaRicaFeController;

Route::get('/fe-cr/cabys', [CostaRicaFeCatalogController::class, 'cabys']);
Route::get('/fe-cr/contribuyente', [CostaRicaFeCatalogController::class, 'contribuyente']);
Route::get('/fe-cr/exoneracion', [CostaRicaFeCatalogController::class, 'exoneracion']);
Route::get('/fe-cr/tipo-cambio-dolar', [CostaRicaFeCatalogController::class, 'tipoCambioDolar']);

Route::post('/emitirFeCrVenta', [CostaRicaFeController::class, 'emitirFactura']);
Route::post('/emitirFeCrTiqueteVenta', [CostaRicaFeController::class, 'emitirTiquete']);
Route::post('/emitirFeCrNotaCreditoDevolucion', [CostaRicaFeController::class, 'emitirNotaCreditoDevolucion']);
Route::post('/emitirFeCrNotaDebitoVenta', [CostaRicaFeController::class, 'emitirNotaDebito']);
Route::post('/consultarFeCrVenta', [CostaRicaFeController::class, 'consultarEstadoVenta']);

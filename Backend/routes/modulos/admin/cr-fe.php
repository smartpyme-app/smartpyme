<?php

use App\Http\Controllers\Api\Admin\CostaRicaFeCatalogController;
use App\Http\Controllers\Api\Admin\CostaRicaFeController;

Route::get('/fe-cr/cabys', [CostaRicaFeCatalogController::class, 'cabys']);
Route::get('/fe-cr/contribuyente', [CostaRicaFeCatalogController::class, 'contribuyente']);
Route::get('/fe-cr/exoneracion', [CostaRicaFeCatalogController::class, 'exoneracion']);
Route::get('/fe-cr/tipo-cambio-dolar', [CostaRicaFeCatalogController::class, 'tipoCambioDolar']);
Route::get('/fe-cr/departamentos', [CostaRicaFeCatalogController::class, 'departamentos']);
Route::get('/fe-cr/municipios', [CostaRicaFeCatalogController::class, 'municipios']);
Route::get('/fe-cr/distritos', [CostaRicaFeCatalogController::class, 'distritos']);

Route::post('/emitirFeCrVenta', [CostaRicaFeController::class, 'emitirFactura']);
Route::post('/emitirFeCrTiqueteVenta', [CostaRicaFeController::class, 'emitirTiquete']);
Route::post('/emitirFeCrCompra', [CostaRicaFeController::class, 'emitirFacturaElectronicaCompra']);
Route::post('/emitirFeCrGasto', [CostaRicaFeController::class, 'emitirFacturaElectronicaGasto']);
Route::post('/emitirFeCrNotaCreditoDevolucion', [CostaRicaFeController::class, 'emitirNotaCreditoDevolucion']);
Route::post('/emitirFeCrNotaDebitoVenta', [CostaRicaFeController::class, 'emitirNotaDebito']);
Route::post('/consultarFeCrVenta', [CostaRicaFeController::class, 'consultarEstadoVenta']);
Route::post('/consultarFeCrDevolucion', [CostaRicaFeController::class, 'consultarEstadoDevolucion']);
Route::post('/consultarFeCrCompra', [CostaRicaFeController::class, 'consultarEstadoCompra']);
Route::post('/consultarFeCrGasto', [CostaRicaFeController::class, 'consultarEstadoGasto']);
Route::post('/consultarFeCrNotaDebitoVenta', [CostaRicaFeController::class, 'consultarEstadoNotaDebitoVenta']);

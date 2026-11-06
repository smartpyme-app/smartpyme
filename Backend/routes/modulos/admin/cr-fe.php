<?php

use App\Http\Controllers\Api\Admin\CostaRicaFeCatalogController;
use App\Http\Controllers\Api\Admin\CostaRicaFeController;

$pEmitirFe = config('permissions.PERMISSION_VENTAS.facturacion_electronica.emitir');

Route::get('/fe-cr/cabys', [CostaRicaFeCatalogController::class, 'cabys']);
Route::get('/fe-cr/contribuyente', [CostaRicaFeCatalogController::class, 'contribuyente']);
Route::get('/fe-cr/exoneracion', [CostaRicaFeCatalogController::class, 'exoneracion']);
Route::get('/fe-cr/tipo-cambio-dolar', [CostaRicaFeCatalogController::class, 'tipoCambioDolar']);
Route::get('/fe-cr/bccr-tipo-cambio', [CostaRicaFeCatalogController::class, 'bccrTipoCambio']);
Route::get('/fe-cr/departamentos', [CostaRicaFeCatalogController::class, 'departamentos']);
Route::get('/fe-cr/municipios', [CostaRicaFeCatalogController::class, 'municipios']);
Route::get('/fe-cr/distritos', [CostaRicaFeCatalogController::class, 'distritos']);

Route::post('/emitirFeCrVenta', [CostaRicaFeController::class, 'emitirFactura'])->middleware('permission:'.$pEmitirFe);
Route::post('/emitirFeCrTiqueteVenta', [CostaRicaFeController::class, 'emitirTiquete'])->middleware('permission:'.$pEmitirFe);
Route::post('/emitirFeCrCompra', [CostaRicaFeController::class, 'emitirFacturaElectronicaCompra'])->middleware('permission:'.$pEmitirFe);
Route::post('/emitirFeCrGasto', [CostaRicaFeController::class, 'emitirFacturaElectronicaGasto'])->middleware('permission:'.$pEmitirFe);
Route::post('/emitirFeCrNotaCreditoDevolucion', [CostaRicaFeController::class, 'emitirNotaCreditoDevolucion'])->middleware('permission:'.$pEmitirFe);
Route::post('/emitirFeCrNotaDebitoVenta', [CostaRicaFeController::class, 'emitirNotaDebito'])->middleware('permission:'.$pEmitirFe);
Route::post('/consultarFeCrVenta', [CostaRicaFeController::class, 'consultarEstadoVenta']);
Route::post('/consultarFeCrDevolucion', [CostaRicaFeController::class, 'consultarEstadoDevolucion']);
Route::post('/consultarFeCrCompra', [CostaRicaFeController::class, 'consultarEstadoCompra']);
Route::post('/consultarFeCrGasto', [CostaRicaFeController::class, 'consultarEstadoGasto']);
Route::post('/consultarFeCrNotaDebitoVenta', [CostaRicaFeController::class, 'consultarEstadoNotaDebitoVenta']);

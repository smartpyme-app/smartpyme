<?php

use App\Http\Controllers\Api\Admin\CostaRicaFeController;

Route::post('/emitirFeCrVenta', [CostaRicaFeController::class, 'emitirFactura']);
Route::post('/emitirFeCrTiqueteVenta', [CostaRicaFeController::class, 'emitirTiquete']);
Route::post('/emitirFeCrNotaCreditoDevolucion', [CostaRicaFeController::class, 'emitirNotaCreditoDevolucion']);
Route::post('/emitirFeCrNotaDebitoVenta', [CostaRicaFeController::class, 'emitirNotaDebito']);
Route::post('/consultarFeCrVenta', [CostaRicaFeController::class, 'consultarEstadoVenta']);

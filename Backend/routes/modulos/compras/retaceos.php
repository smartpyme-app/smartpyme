<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Compras\Retaceo\RetaceoController;
// Rutas de Retaceo

Route::get('retaceos', [RetaceoController::class, 'index']);
Route::post('retaceo', [RetaceoController::class, 'store']);
Route::get('retaceo/{id}', [RetaceoController::class, 'show']);
Route::put('retaceo/{id}', [RetaceoController::class, 'update']);
Route::delete('retaceo/{id}', [RetaceoController::class, 'destroy']);

// Actualizar estado del retaceo
Route::post('retaceo/estado', [RetaceoController::class, 'actualizarEstado']);

// Cargar datos relacionados
Route::get('retaceo_gastos', [RetaceoController::class, 'retaceoGastos']);

Route::get('retaceo_distribucion', [RetaceoController::class, 'retaceoDistribucion']);

Route::post('retaceo/calcular', [RetaceoController::class, 'calcularDistribucion']);

// Imprimir retaceo
Route::get('retaceo/imprimir/{id}', [RetaceoController::class, 'imprimir']);

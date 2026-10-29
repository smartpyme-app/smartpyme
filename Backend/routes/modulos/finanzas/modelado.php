<?php

use App\Http\Controllers\Api\Finanzas\ModeladoController;
use Illuminate\Support\Facades\Route;

Route::get('finanzas/modelado', [ModeladoController::class, 'show']);
Route::put('finanzas/modelado/clasificacion', [ModeladoController::class, 'guardarClasificacion']);
Route::delete('finanzas/modelado/clasificacion', [ModeladoController::class, 'borrarClasificacion']);
Route::post('finanzas/modelado/calendario', [ModeladoController::class, 'crearCalendario']);
Route::put('finanzas/modelado/calendario/{id}', [ModeladoController::class, 'actualizarCalendario']);
Route::delete('finanzas/modelado/calendario/{id}', [ModeladoController::class, 'borrarCalendario']);
Route::put('finanzas/modelado/presupuesto', [ModeladoController::class, 'guardarPresupuesto']);
Route::put('finanzas/modelado/pronostico', [ModeladoController::class, 'guardarPronostico']);

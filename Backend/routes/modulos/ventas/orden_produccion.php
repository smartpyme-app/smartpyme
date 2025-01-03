<?php

use App\Http\Controllers\Api\Ventas\OrdenProduccion\OrdenProduccionController;
use Illuminate\Support\Facades\Route;

Route::get('/ordenes-produccion', [OrdenProduccionController::class, 'index']);
Route::get('/orden-produccion/{id}', [OrdenProduccionController::class, 'read']);
Route::post('/orden-produccion', [OrdenProduccionController::class, 'store']);
Route::put('/orden-produccion/{id}', [OrdenProduccionController::class, 'update']);
Route::post('/orden-produccion/cambiar-estado', [OrdenProduccionController::class, 'cambiarEstado']);
Route::post('/orden-produccion/anular', [OrdenProduccionController::class, 'anular']);
?>
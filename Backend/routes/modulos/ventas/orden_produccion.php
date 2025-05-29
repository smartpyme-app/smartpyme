<?php

use App\Http\Controllers\Api\Ventas\OrdenProduccion\OrdenProduccionController;
use Illuminate\Support\Facades\Route;

Route::get('/ordenes-produccion', [OrdenProduccionController::class, 'index']);
Route::get('/orden-produccion/{id}', [OrdenProduccionController::class, 'read']);
Route::post('/orden-produccion', [OrdenProduccionController::class, 'store']);
Route::put('/orden-produccion/{id}', [OrdenProduccionController::class, 'update']);
Route::post('/orden-produccion/cambiar-estado', [OrdenProduccionController::class, 'cambiarEstado']);
Route::post('/orden-produccion/cambiar-estado-orden', [OrdenProduccionController::class, 'changeStateOrden']);
Route::post('/orden-produccion/anular', [OrdenProduccionController::class, 'anular']);
Route::get('/orden-produccion/imprimir/{id}', [OrdenProduccionController::class, 'imprimir']);
//Route::get('orden-produccion/{id}/documento', [OrdenProduccionController::class, 'getDocumento']);
//ordenes-produccion/exportar/documento
Route::get('/ordenes-produccion/exportar/documento', [OrdenProduccionController::class, 'getDocumento']);

?>
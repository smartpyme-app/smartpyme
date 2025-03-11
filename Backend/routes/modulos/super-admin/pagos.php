<?php

use App\Http\Controllers\Api\Admin\EmpresasController;
use App\Http\Controllers\Api\SuperAdmin\PagosController;
use App\Http\Controllers\Api\SuperAdmin\PlanesController;
use Illuminate\Support\Facades\Route;

Route::get('/pagos',                 [PagosController::class, 'index']);
Route::post('/pago',                 [PagosController::class, 'store']);
Route::get('/pago/{id}',             [PagosController::class, 'read']);
Route::delete('/pago/{id}',          [PagosController::class, 'delete']);

Route::get('/pago/generar-venta/{id}',          [PagosController::class, 'generarVenta']);

Route::post('/pago/new',             [PagosController::class, 'newPayment']);

Route::get('/empresas/obtenerEmpresas', [EmpresasController::class, 'getEmpresasforSelect']);
Route::get('/planes/obtenerPlanes', [PlanesController::class, 'getPlanesforSelect']);

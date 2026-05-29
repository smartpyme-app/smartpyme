<?php

use App\Http\Controllers\Api\Ventas\Clientes\ClientesController;
use Illuminate\Support\Facades\Route;

Route::get('/superadmin/clientes', [ClientesController::class, 'indexSuperAdmin'])
    ->middleware('superadmin');

Route::post('/superadmin/cliente/update', [ClientesController::class, 'updateSuperAdmin'])
    ->middleware('superadmin');
Route::post('/superadmin/cliente', [ClientesController::class, 'storeSuperAdmin'])
    ->middleware('superadmin');
Route::get('/superadmin/cliente/{id}', [ClientesController::class, 'readSuperAdmin'])
    ->middleware('superadmin');

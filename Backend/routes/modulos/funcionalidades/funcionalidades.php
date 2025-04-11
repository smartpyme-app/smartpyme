<?php

use App\Http\Controllers\Api\Admin\EmpresasController;
use App\Http\Controllers\Api\Admin\EmpresasFuncionalidadesController;
use Illuminate\Support\Facades\Route;

// Rutas para gestión administrativa (solo accesibles para usuarios admin)

Route::get('/empresas-funcionalidades', [EmpresasFuncionalidadesController::class, 'index'])->name('admin.empresas-funcionalidades');
Route::get('/empresas/list', [EmpresasController::class, 'list'])->middleware('superadmin');

// API para cargar datos
Route::get('/empresas/{id}/funcionalidades', [EmpresasFuncionalidadesController::class, 'getEmpresaFuncionalidades']);

// API para actualizar datos
Route::post('/empresas/funcionalidades/actualizar', [EmpresasFuncionalidadesController::class, 'actualizarFuncionalidad']);
Route::post('/empresas/funcionalidades/actualizar-multiple', [EmpresasFuncionalidadesController::class, 'actualizarMultiple']);

// Rutas para API con autenticación
Route::middleware(['auth'])->group(function () {
    Route::get('/configuracion-funcionalidad/{slug}', [EmpresasFuncionalidadesController::class, 'obtenerConfiguracion']);
});

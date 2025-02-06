<?php 

use App\Http\Controllers\Api\Licencias\LicenciasController;
use App\Http\Controllers\Api\Licencias\EmpresasController;

    Route::get('/licencias',                  [LicenciasController::class, 'index']);
    Route::get('/licencia/{id}',              [LicenciasController::class, 'read']);
    Route::post('/licencia',                  [LicenciasController::class, 'store']);
    Route::delete('/licencia/{id}',           [LicenciasController::class, 'delete']);

    Route::get('/licencias/empresas',                  [EmpresasController::class, 'index']);
    Route::post('/licencia/empresa',                  [EmpresasController::class, 'store']);
    Route::delete('/licencia/empresa/{id}',           [EmpresasController::class, 'delete']);

    Route::get('/licencias/empresas/list',    [EmpresasController::class, 'list']);
    Route::get('/licencias/usuarios',                  [LicenciasController::class, 'usuarios']);

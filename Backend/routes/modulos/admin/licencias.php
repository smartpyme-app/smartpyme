<?php 

use App\Http\Controllers\Api\Licencias\LicenciasController;
use App\Http\Controllers\Api\Licencias\EmpresasController;

    Route::get('/licencias',                  [LicenciasController::class, 'index']);
    Route::get('/licencia/{id}',              [LicenciasController::class, 'read']);
    Route::post('/licencia',                  [LicenciasController::class, 'store']);
    Route::delete('/licencia/{id}',           [LicenciasController::class, 'delete']);

    Route::get('/licencias/empresas',                  [EmpresasController::class, 'index'])->middleware('permission:organizacion.empresas.ver');
    Route::post('/licencia/empresa',                  [EmpresasController::class, 'store'])->middleware('permission:organizacion.empresas.crear');
    Route::delete('/licencia/empresa/{id}',           [EmpresasController::class, 'delete'])->middleware('permission:organizacion.empresas.eliminar');

    Route::get('/licencias/empresas/list',    [EmpresasController::class, 'list'])->middleware('permission:organizacion.empresas.ver');
    Route::get('/licencias/empresas/select',  [EmpresasController::class, 'selectParaLicencia'])->middleware('permission:organizacion.empresas.crear');
    Route::get('/licencias/usuarios',                  [LicenciasController::class, 'usuarios']);

?>

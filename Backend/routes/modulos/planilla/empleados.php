<?php

use App\Http\Controllers\Api\Planilla\EmpleadosController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'empleados', 'middleware' => ['jwt.auth']], function () {
    Route::controller(EmpleadosController::class)->group(function () {
        Route::get('/', 'index')->middleware('permission:planilla.empleados.ver');
        Route::get('/list', 'list')->middleware('permission:planilla.empleados.ver');
        Route::get('/{id}', 'show')->middleware('permission:planilla.empleados.ver');
        Route::post('/', 'store')->middleware('permission:planilla.empleados.crear');
        Route::put('/{id}', 'update')->middleware('permission:planilla.empleados.editar');
        Route::delete('/{id}', 'destroy')->middleware('permission:planilla.empleados.eliminar');
        Route::post('/cambiar-estado/{id}', 'cambiarEstado')->middleware('permission:planilla.empleados.editar');
        Route::post('/{id}/dar-baja', 'darBaja')->middleware('permission:planilla.empleados.editar');
        Route::post('/{id}/dar-alta', 'darAlta')->middleware('permission:planilla.empleados.editar');
        Route::get('/{id}/historialesContratos', 'getHistorialesContratos')->middleware('permission:planilla.empleados.ver');
        Route::get('/{id}/historialesBajas', 'getHistorialesBajas')->middleware('permission:planilla.empleados.ver');
        Route::get('documentos/{id}/descargar', 'descargarDocumento')->middleware('permission:planilla.empleados.ver');
        Route::get('contratos/{id}/descargar', 'descargarContrato')->middleware('permission:planilla.empleados.ver');
        Route::post('{id}/documentos', 'subirDocumentos')->middleware('permission:planilla.empleados.editar');
        Route::get('{id}/documentos', 'getDocumentos')->middleware('permission:planilla.empleados.ver');
        Route::post('/importar', 'importar')->middleware('permission:planilla.empleados.crear');
    });
});

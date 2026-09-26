<?php

use App\Http\Controllers\Api\Planilla\AreasEmpresaController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'area-empresa', 'middleware' => ['jwt.auth']], function () {
    Route::controller(AreasEmpresaController::class)->group(function () {
        Route::get('/list', 'list');
        Route::get('/list_departamentos', 'list_departamentos');
        Route::get('/exportar', 'exportar')->middleware('permission:administracion.areas.ver');
        Route::post('/cambiar-estado-multiple', 'cambiarEstadoMultiple')->middleware('permission:administracion.areas.editar');
        Route::get('/', 'index')->middleware('permission:administracion.areas.ver');
        Route::post('/', 'store')->middleware('permission:administracion.areas.crear|administracion.areas.editar');
        Route::get('/{id}', 'show')->middleware('permission:administracion.areas.ver');
        Route::delete('/{id}', 'destroy')->middleware('permission:administracion.areas.eliminar');
    });
});

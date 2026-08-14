<?php

use App\Http\Controllers\Api\Planilla\DepartamentosEmpresaController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'departamentosPlanilla', 'middleware' => ['jwt.auth']], function () {
    Route::controller(DepartamentosEmpresaController::class)->group(function () {
        Route::get('/', 'index')->middleware('permission:planilla.empleados.ver');
        Route::get('/list', 'list')->middleware('permission:planilla.empleados.ver');
        Route::post('/update', 'store')->middleware('permission:planilla.empleados.editar');
        Route::post('/changeState/{id}', 'changeState')->middleware('permission:planilla.empleados.editar');
        Route::get('/{id}/areas', 'areas')->middleware('permission:planilla.empleados.ver');
        Route::get('/{id}', 'show')->middleware('permission:planilla.empleados.ver');
        Route::post('/', 'store')->middleware('permission:planilla.empleados.crear');
        Route::delete('/{id}', 'destroy')->middleware('permission:planilla.empleados.eliminar');
    });
});

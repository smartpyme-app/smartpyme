<?php

use App\Http\Controllers\Api\Planilla\CargosEmpresaController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'cargos', 'middleware' => ['jwt.auth']], function () {
    Route::controller(CargosEmpresaController::class)->group(function () {
        Route::get('/', 'index')->middleware('permission:planilla.empleados.ver');
        Route::get('/list', 'list')->middleware('permission:planilla.empleados.ver');
        Route::get('/{id}', 'show')->middleware('permission:planilla.empleados.ver');
        Route::post('/', 'store')->middleware('permission:planilla.empleados.crear');
    });
});

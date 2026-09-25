<?php 

use App\Http\Controllers\Api\Planilla\DepartamentosEmpresaController;
use App\Http\Controllers\AreasEmpresaController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'departamentosEmpresa', 'middleware' => ['jwt.auth']], function () {
    Route::controller(DepartamentosEmpresaController::class)->group(function () {
        Route::get('/', 'index')->middleware('permission:administracion.departamentos.ver');
        Route::get('/list', 'list');
        Route::get('/{id}', 'show')->middleware('permission:administracion.departamentos.ver');
        Route::post('/', 'store')->middleware('permission:administracion.departamentos.crear');
        Route::post('/update', 'update')->middleware('permission:administracion.departamentos.editar');
        Route::post('/changeState/{id}', 'changeState')->middleware('permission:administracion.departamentos.editar');
    });

});
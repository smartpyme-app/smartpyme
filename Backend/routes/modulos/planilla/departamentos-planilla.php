<?php

use App\Http\Controllers\Api\Planilla\DepartamentosEmpresaController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'departamentosPlanilla', 'middleware' => ['auth:api']], function () {
    Route::controller(DepartamentosEmpresaController::class)->group(function () {
        Route::get('/', 'index');
        Route::get('/list', 'list');
        Route::post('/update', 'store');
        Route::post('/changeState/{id}', 'changeState');
        Route::get('/{id}/areas', 'areas');
        Route::get('/{id}', 'show');
        Route::post('/', 'store');
        Route::delete('/{id}', 'destroy');
    });
});

<?php

use App\Http\Controllers\Api\Planilla\AreasEmpresaController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'area-empresa', 'middleware' => ['auth:api']], function () {
    Route::controller(AreasEmpresaController::class)->group(function () {
        Route::get('/list', 'list');
        Route::get('/list_departamentos', 'list_departamentos');
        Route::get('/exportar', 'exportar');
        Route::post('/cambiar-estado-multiple', 'cambiarEstadoMultiple');
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/{id}', 'show');
        Route::delete('/{id}', 'destroy');
    });
});

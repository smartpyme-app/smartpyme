<?php 

use App\Http\Controllers\Api\Planilla\DepartamentosEmpresaController;
use App\Http\Controllers\AreasEmpresaController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'departamentosEmpresa', 'middleware' => ['auth:api']], function () {
    Route::controller(DepartamentosEmpresaController::class)->group(function () {
        Route::get('/', 'index');
        Route::get('/list', 'list');
        Route::get('/{id}', 'show');
        Route::post('/', 'store');
        Route::post('/update', 'update');
        Route::post('/changeState/{id}', 'changeState');
    });

});
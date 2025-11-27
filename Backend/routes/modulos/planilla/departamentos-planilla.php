<?php 

use App\Http\Controllers\Api\Planilla\DepartamentosEmpresaController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'departamentosPlanilla', 'middleware' => ['jwt.auth']], function () {
    Route::controller(DepartamentosEmpresaController::class)->group(function () {
        Route::get('/', 'index');
        Route::get('/list', 'list');
        Route::get('/{id}', 'show');
        Route::post('/', 'store');
    });
});
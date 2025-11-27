<?php 

use App\Http\Controllers\Api\Planilla\CargosEmpresaController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'cargos', 'middleware' => ['jwt.auth']], function () {
    Route::controller(CargosEmpresaController::class)->group(function () {
        Route::get('/', 'index');
        Route::get('/list', 'list');
        Route::get('/{id}', 'show');
        Route::post('/', 'store');
    });
});
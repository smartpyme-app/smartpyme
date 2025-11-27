<?php 

use App\Http\Controllers\Api\Planilla\EmpleadosController;
use App\Http\Controllers\Api\Planilla\HistorialContratosController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'historial-contratos', 'middleware' => ['jwt.auth']], function () {
    Route::controller(HistorialContratosController::class)->group(function () {
        Route::get('/empleado/{id}', 'porEmpleado');
        Route::post('/', 'store');
    });
});
<?php

use App\Http\Controllers\Api\Planilla\PlanillasController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'planillas', 'middleware' => ['auth:api']], function () {
    Route::controller(PlanillasController::class)->group(function () {
        Route::get('/', 'index');
        Route::get('{id}/boletas', 'generarBoletas');
        Route::post('/generate', 'store');
        Route::get('/list', 'list');
        Route::get('/detalles', 'show');
        Route::get('plantilla-importacion', 'descargarPlantilla');
        Route::get('{id}/excel', 'exportExcel');
        Route::get('{id}/pdf', 'exportPDF');
        Route::post('/', 'store');
        Route::post('update/{id}', 'update');
        Route::post('{id}/pagar','processPayment');
        Route::get('detalles/{id}/boleta', 'generarBoletaIndividual');
        Route::post('detalles/editar/{id}', 'updateDetailsPayroll');
        Route::post('detalles/retirar/{id}', 'withdrawPayroll');
        Route::post('detalles/incluir/{id}', 'includePayroll');
        Route::post('/aprobar/{id}', 'approvePayroll');
        Route::post('/duplicate', 'store');
        Route::post('/importar', 'importar');
        Route::delete('/{id}', 'destroy');
    });
});

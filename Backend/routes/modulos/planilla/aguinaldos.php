<?php

use App\Http\Controllers\Api\Planilla\AguinaldosController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'aguinaldos', 'middleware' => ['auth:api']], function () {
    Route::controller(AguinaldosController::class)->group(function () {
        Route::get('/', 'index')->middleware('permission:planilla.registros.ver');
        Route::post('/', 'store')->middleware('permission:planilla.registros.crear');
        Route::get('/{id}', 'show')->middleware('permission:planilla.registros.ver');
        Route::put('/{id}/fecha-calculo', 'actualizarFechaCalculo')->middleware('permission:planilla.registros.editar');
        Route::post('/{id}/agregar-empleado', 'agregarEmpleado')->middleware('permission:planilla.registros.editar');
        Route::post('/{id}/pagar', 'processPayment')->middleware('permission:planilla.registros.editar');
        Route::get('/{id}/excel', 'exportExcel')->middleware('permission:planilla.registros.ver');
        Route::get('/{id}/pdf', 'exportPDF')->middleware('permission:planilla.registros.ver');
        Route::delete('/{id}', 'destroy')->middleware('permission:planilla.registros.eliminar');
        Route::post('/sugerencia', 'obtenerSugerenciaAguinaldo')->middleware('permission:planilla.registros.ver');
        Route::post('/preview', 'calcularPreview')->middleware('permission:planilla.registros.ver');
    });
});

Route::group(['prefix' => 'aguinaldo-detalles', 'middleware' => ['auth:api']], function () {
    Route::controller(AguinaldosController::class)->group(function () {
        Route::put('/{id}', 'actualizarEmpleado')->middleware('permission:planilla.registros.editar');
        Route::delete('/{id}', 'eliminarEmpleado')->middleware('permission:planilla.registros.eliminar');
    });
});

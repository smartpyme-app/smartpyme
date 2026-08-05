<?php

use App\Http\Controllers\Api\Planilla\PlanillaController;
use App\Http\Controllers\Api\Planilla\PlanillaDetalleController;
use App\Http\Controllers\Api\Planilla\PlanillaAprobacionController;
use App\Http\Controllers\Api\Planilla\PlanillaCalculoController;
use App\Http\Controllers\Api\Planilla\PlanillaExportController;
use App\Http\Controllers\Api\Planilla\PlanillaImportController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'planillas', 'middleware' => ['jwt.auth']], function () {
    Route::controller(PlanillaController::class)->group(function () {
        Route::get('/', 'index')->middleware('permission:planilla.registros.ver');
        Route::post('/', 'store')->middleware('permission:planilla.registros.crear');
        Route::post('/generate', 'store')->middleware('permission:planilla.registros.crear');
        Route::get('/detalles', 'show')->middleware('permission:planilla.registros.ver');
        Route::post('update/{id}', 'update')->middleware('permission:planilla.registros.editar');
        Route::delete('/{id}', 'destroy')->middleware('permission:planilla.registros.eliminar');
    });

    Route::controller(PlanillaDetalleController::class)->group(function () {
        Route::post('detalles/editar/{id}', 'update')->middleware('permission:planilla.registros.editar');
        Route::post('detalles/retirar/{id}', 'retirar')->middleware('permission:planilla.registros.editar');
        Route::post('detalles/incluir/{id}', 'incluir')->middleware('permission:planilla.registros.editar');
    });

    Route::controller(PlanillaAprobacionController::class)->group(function () {
        Route::post('/aprobar/{id}', 'approve')->middleware('permission:planilla.registros.editar');
        Route::post('/revertir/{id}', 'revert')->middleware('permission:planilla.registros.editar');
        Route::post('{id}/pagar', 'processPayment')->middleware('permission:planilla.registros.editar');
    });

    Route::controller(PlanillaCalculoController::class)->group(function () {
        Route::post('recalculo-renta/{id}', 'recalcularRenta')->middleware('permission:planilla.registros.editar');
        Route::get('detalle-calculo-renta/{detalleId}', 'obtenerDetalleCalculoRenta')->middleware('permission:planilla.registros.ver');
        Route::post('validar-calculo-renta', 'validarCalculoRenta')->middleware('permission:planilla.registros.ver');
    });

    Route::controller(PlanillaExportController::class)->group(function () {
        Route::get('{id}/excel', 'exportExcel')->middleware('permission:planilla.registros.ver');
        Route::get('{id}/pdf', 'exportPDF')->middleware('permission:planilla.registros.ver');
        Route::get('{id}/boletas', 'generarBoletas')->middleware('permission:planilla.registros.ver');
        Route::get('detalles/{id}/boleta', 'generarBoletaIndividual')->middleware('permission:planilla.registros.ver');
        Route::get('descuentos-patronales/{id}', 'obtenerDescuentosPatronales')->middleware('permission:planilla.registros.ver');
        Route::get('detalles/exportar', 'exportarDetallesPlanilla')->middleware('permission:planilla.registros.ver');
        Route::get('plantilla-importacion', 'descargarPlantilla')->middleware('permission:planilla.registros.ver');
    });

    Route::controller(PlanillaImportController::class)->group(function () {
        Route::post('/importar', 'importar')->middleware('permission:planilla.registros.crear');
    });
});

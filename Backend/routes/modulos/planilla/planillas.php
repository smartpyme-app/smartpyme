<?php

use App\Http\Controllers\Api\Planilla\PlanillaController;
use App\Http\Controllers\Api\Planilla\PlanillaDetalleController;
use App\Http\Controllers\Api\Planilla\PlanillaAprobacionController;
use App\Http\Controllers\Api\Planilla\PlanillaCalculoController;
use App\Http\Controllers\Api\Planilla\PlanillaExportController;
use App\Http\Controllers\Api\Planilla\PlanillaImportController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'planillas', 'middleware' => ['jwt.auth']], function () {
    
    // CRUD básico de planillas
    Route::controller(PlanillaController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::post('/generate', 'store'); // Alias para compatibilidad
        Route::get('/detalles', 'show');
        Route::post('update/{id}', 'update');
        Route::delete('/{id}', 'destroy');
    });

    // Gestión de detalles de planilla
    Route::controller(PlanillaDetalleController::class)->group(function () {
        Route::post('detalles/editar/{id}', 'update');
        Route::post('detalles/retirar/{id}', 'retirar');
        Route::post('detalles/incluir/{id}', 'incluir');
    });

    // Aprobación y pago de planillas
    Route::controller(PlanillaAprobacionController::class)->group(function () {
        Route::post('/aprobar/{id}', 'approve');
        Route::post('/revertir/{id}', 'revert');
        Route::post('{id}/pagar', 'processPayment');
    });

    // Cálculos y recálculos
    Route::controller(PlanillaCalculoController::class)->group(function () {
        Route::post('recalculo-renta/{id}', 'recalcularRenta');
        Route::get('detalle-calculo-renta/{detalleId}', 'obtenerDetalleCalculoRenta');
        Route::post('validar-calculo-renta', 'validarCalculoRenta');
    });

    // Exportaciones
    Route::controller(PlanillaExportController::class)->group(function () {
        Route::get('{id}/excel', 'exportExcel');
        Route::get('{id}/pdf', 'exportPDF');
        Route::get('{id}/boletas', 'generarBoletas');
        Route::get('detalles/{id}/boleta', 'generarBoletaIndividual');
        Route::get('descuentos-patronales/{id}', 'obtenerDescuentosPatronales');
        Route::get('detalles/exportar', 'exportarDetallesPlanilla');
        Route::get('plantilla-importacion', 'descargarPlantilla');
    });

    // Importaciones
    Route::controller(PlanillaImportController::class)->group(function () {
        Route::post('/importar', 'importar');
    });
});

<?php

use App\Http\Controllers\Api\Clinica\DiagnosticosController;
use Illuminate\Support\Facades\Route;

Route::middleware(['verificar.funcionalidad:clinica-pacientes'])->group(function () {
    Route::get('clinica/diagnosticos-catalogo', [DiagnosticosController::class, 'indexCatalogo'])
        ->middleware('permission:clinica.diagnosticos.ver|clinica.diagnosticos.editar');
    Route::post('clinica/diagnosticos-catalogo', [DiagnosticosController::class, 'storeCatalogo'])
        ->middleware('permission:clinica.diagnosticos.editar');
    Route::put('clinica/diagnosticos-catalogo/{id}', [DiagnosticosController::class, 'updateCatalogo'])
        ->middleware('permission:clinica.diagnosticos.editar');

    Route::get('clinica/pacientes/{id}/diagnosticos', [DiagnosticosController::class, 'index'])
        ->middleware('permission:clinica.diagnosticos.ver|clinica.expediente.ver');
    Route::get('clinica/pacientes/{id}/diagnosticos/{idDiagnostico}', [DiagnosticosController::class, 'show'])
        ->middleware('permission:clinica.diagnosticos.ver');
    Route::post('clinica/pacientes/{id}/diagnosticos', [DiagnosticosController::class, 'store'])
        ->middleware('permission:clinica.diagnosticos.crear');
    Route::put('clinica/pacientes/{id}/diagnosticos/{idDiagnostico}', [DiagnosticosController::class, 'update'])
        ->middleware('permission:clinica.diagnosticos.editar');
    Route::patch('clinica/pacientes/{id}/diagnosticos/{idDiagnostico}/cerrar', [DiagnosticosController::class, 'cerrar'])
        ->middleware('permission:clinica.diagnosticos.editar');
    Route::patch('clinica/pacientes/{id}/diagnosticos/{idDiagnostico}/anular', [DiagnosticosController::class, 'anular'])
        ->middleware('permission:clinica.diagnosticos.editar');
    Route::post('clinica/pacientes/{id}/diagnosticos/{idDiagnostico}/corregir', [DiagnosticosController::class, 'corregir'])
        ->middleware('permission:clinica.diagnosticos.editar');
});

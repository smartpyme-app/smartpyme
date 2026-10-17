<?php

use App\Http\Controllers\Api\Clinica\VacunasController;
use Illuminate\Support\Facades\Route;

Route::middleware(['verificar.funcionalidad:clinica-pacientes'])->group(function () {
    Route::get('clinica/vacunas-catalogo', [VacunasController::class, 'indexCatalogo'])
        ->middleware('permission:clinica.vacunas.configurar|clinica.vacunas.ver');
    Route::post('clinica/vacunas-catalogo', [VacunasController::class, 'storeCatalogo'])
        ->middleware('permission:clinica.vacunas.configurar');
    Route::put('clinica/vacunas-catalogo/{idCatalogo}', [VacunasController::class, 'updateCatalogo'])
        ->middleware('permission:clinica.vacunas.configurar');
    Route::get('clinica/pacientes/{id}/vacunas-catalogo', [VacunasController::class, 'catalogoPaciente'])
        ->middleware('permission:clinica.vacunas.crear|clinica.vacunas.ver');
    Route::get('clinica/pacientes/{id}/vacunas', [VacunasController::class, 'index'])
        ->middleware('permission:clinica.vacunas.ver|clinica.expediente.ver');
    Route::get('clinica/pacientes/{id}/vacunas/{idVacuna}', [VacunasController::class, 'show'])
        ->middleware('permission:clinica.vacunas.ver');
    Route::post('clinica/pacientes/{id}/vacunas', [VacunasController::class, 'store'])
        ->middleware('permission:clinica.vacunas.crear');
    Route::patch('clinica/pacientes/{id}/vacunas/{idVacuna}/anular', [VacunasController::class, 'anular'])
        ->middleware('permission:clinica.vacunas.anular');
});

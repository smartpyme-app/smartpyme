<?php

use App\Http\Controllers\Api\Clinica\ConsultasController;
use Illuminate\Support\Facades\Route;

Route::middleware(['verificar.funcionalidad:clinica-pacientes'])->group(function () {
    Route::get('clinica/pacientes/{id}/consultas', [ConsultasController::class, 'index'])
        ->middleware('permission:clinica.consultas.ver|clinica.expediente.ver');
    Route::get('clinica/pacientes/{id}/consultas/{idConsulta}', [ConsultasController::class, 'show'])
        ->middleware('permission:clinica.consultas.ver');
    Route::post('clinica/pacientes/{id}/consultas', [ConsultasController::class, 'store'])
        ->middleware('permission:clinica.consultas.crear');
    Route::put('clinica/pacientes/{id}/consultas/{idConsulta}', [ConsultasController::class, 'update'])
        ->middleware('permission:clinica.consultas.editar');
    Route::patch('clinica/pacientes/{id}/consultas/{idConsulta}/cerrar', [ConsultasController::class, 'cerrar'])
        ->middleware('permission:clinica.consultas.editar');
    Route::patch('clinica/pacientes/{id}/consultas/{idConsulta}/anular', [ConsultasController::class, 'anular'])
        ->middleware('permission:clinica.consultas.editar');
});

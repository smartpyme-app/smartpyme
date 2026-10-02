<?php

use App\Http\Controllers\Api\Clinica\ProfesionalesController;
use Illuminate\Support\Facades\Route;

Route::middleware(['verificar.funcionalidad:clinica-profesionales'])->group(function () {
    Route::get('clinica/profesionales/candidatos', [ProfesionalesController::class, 'candidatos'])
        ->middleware('permission:clinica.profesionales.ver|clinica.profesionales.editar');
    Route::get('clinica/profesionales', [ProfesionalesController::class, 'index'])
        ->middleware('permission:clinica.profesionales.ver|clinica.pacientes.ver');
    Route::get('clinica/profesionales/{id}', [ProfesionalesController::class, 'show'])
        ->middleware('permission:clinica.profesionales.ver');
    Route::post('clinica/profesionales', [ProfesionalesController::class, 'store'])
        ->middleware('permission:clinica.profesionales.editar');
    Route::patch('clinica/profesionales/{id}/estado', [ProfesionalesController::class, 'estado'])
        ->middleware('permission:clinica.profesionales.editar');
});

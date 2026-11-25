<?php

use App\Http\Controllers\Api\Clinica\RecetasController;
use Illuminate\Support\Facades\Route;

Route::middleware(['verificar.funcionalidad:clinica-pacientes'])->group(function () {
    Route::get('clinica/pacientes/{id}/recetas', [RecetasController::class, 'index'])
        ->middleware('permission:clinica.recetas.ver|clinica.expediente.ver');
    Route::get('clinica/pacientes/{id}/recetas/{idReceta}', [RecetasController::class, 'show'])
        ->middleware('permission:clinica.recetas.ver');
    Route::post('clinica/pacientes/{id}/recetas', [RecetasController::class, 'store'])
        ->middleware('permission:clinica.recetas.crear');
    Route::put('clinica/pacientes/{id}/recetas/{idReceta}', [RecetasController::class, 'update'])
        ->middleware('permission:clinica.recetas.editar');
    Route::patch('clinica/pacientes/{id}/recetas/{idReceta}/emitir', [RecetasController::class, 'emitir'])
        ->middleware('permission:clinica.recetas.editar');
    Route::patch('clinica/pacientes/{id}/recetas/{idReceta}/anular', [RecetasController::class, 'anular'])
        ->middleware('permission:clinica.recetas.anular');
    Route::get('clinica/pacientes/{id}/recetas/{idReceta}/pdf', [RecetasController::class, 'pdf'])
        ->middleware('permission:clinica.recetas.ver');
});

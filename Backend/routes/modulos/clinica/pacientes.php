<?php

use App\Http\Controllers\Api\Clinica\ExpedientesController;
use App\Http\Controllers\Api\Clinica\PacientesController;
use App\Http\Controllers\Api\Clinica\ResponsablesController;
use Illuminate\Support\Facades\Route;

Route::middleware(['verificar.funcionalidad:clinica-pacientes'])->group(function () {
    Route::get('clinica/especies', [PacientesController::class, 'especies'])
        ->middleware('permission:clinica.pacientes.ver|clinica.pacientes.crear');
    Route::post('clinica/especies', [PacientesController::class, 'storeEspecie'])
        ->middleware('permission:clinica.pacientes.editar');
    Route::post('clinica/especies/{idEspecie}/razas', [PacientesController::class, 'storeRaza'])
        ->middleware('permission:clinica.pacientes.editar');

    Route::get('clinica/pacientes', [PacientesController::class, 'index'])
        ->middleware('permission:clinica.pacientes.ver');
    Route::get('clinica/pacientes/{id}/expediente', [ExpedientesController::class, 'show'])
        ->middleware('permission:clinica.expediente.ver');
    Route::patch('clinica/pacientes/{id}/expediente/estado', [ExpedientesController::class, 'estado'])
        ->middleware('permission:clinica.expediente.archivar');
    Route::get('clinica/pacientes/{id}', [PacientesController::class, 'show'])
        ->middleware('permission:clinica.pacientes.ver');
    Route::post('clinica/pacientes', [PacientesController::class, 'store'])
        ->middleware('permission:clinica.pacientes.crear');
    Route::put('clinica/pacientes/{id}', [PacientesController::class, 'update'])
        ->middleware('permission:clinica.pacientes.editar');
    Route::patch('clinica/pacientes/{id}/estado', [PacientesController::class, 'estado'])
        ->middleware('permission:clinica.pacientes.desactivar');

    Route::post('clinica/pacientes/{id}/responsables', [ResponsablesController::class, 'store'])
        ->middleware('permission:clinica.pacientes.crear|clinica.pacientes.editar');
    Route::put('clinica/pacientes/{id}/responsables/{idVinculo}', [ResponsablesController::class, 'update'])
        ->middleware('permission:clinica.pacientes.editar');
    Route::patch('clinica/pacientes/{id}/responsables/{idVinculo}', [ResponsablesController::class, 'desactivar'])
        ->middleware('permission:clinica.pacientes.editar');
    Route::patch('clinica/pacientes/{id}/alta', [ResponsablesController::class, 'cerrarAlta'])
        ->middleware('permission:clinica.pacientes.editar');
    Route::get('clinica/clientes/{idCliente}/pacientes', [ResponsablesController::class, 'deCliente'])
        ->middleware('permission:clinica.pacientes.ver|ventas.clientes.ver');
});

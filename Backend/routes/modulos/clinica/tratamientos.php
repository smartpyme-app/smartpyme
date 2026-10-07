<?php

use App\Http\Controllers\Api\Clinica\TratamientosController;
use Illuminate\Support\Facades\Route;

Route::middleware(['verificar.funcionalidad:clinica-pacientes'])->group(function () {
    Route::get('clinica/pacientes/{id}/tratamientos', [TratamientosController::class, 'index'])
        ->middleware('permission:clinica.tratamientos.ver|clinica.expediente.ver');
    Route::get('clinica/pacientes/{id}/tratamientos/{idTratamiento}', [TratamientosController::class, 'show'])
        ->middleware('permission:clinica.tratamientos.ver');
    Route::post('clinica/pacientes/{id}/tratamientos', [TratamientosController::class, 'store'])
        ->middleware('permission:clinica.tratamientos.crear');
    Route::put('clinica/pacientes/{id}/tratamientos/{idTratamiento}', [TratamientosController::class, 'update'])
        ->middleware('permission:clinica.tratamientos.editar');
    Route::patch('clinica/pacientes/{id}/tratamientos/{idTratamiento}/iniciar', [TratamientosController::class, 'iniciar'])
        ->middleware('permission:clinica.tratamientos.editar');
    Route::patch('clinica/pacientes/{id}/tratamientos/{idTratamiento}/suspender', [TratamientosController::class, 'suspender'])
        ->middleware('permission:clinica.tratamientos.editar');
    Route::patch('clinica/pacientes/{id}/tratamientos/{idTratamiento}/finalizar', [TratamientosController::class, 'finalizar'])
        ->middleware('permission:clinica.tratamientos.editar');
    Route::post('clinica/pacientes/{id}/tratamientos/{idTratamiento}/avances', [TratamientosController::class, 'storeAvance'])
        ->middleware('permission:clinica.tratamientos.editar');
    Route::post('clinica/pacientes/{id}/tratamientos/{idTratamiento}/terapias', [TratamientosController::class, 'storeTerapia'])
        ->middleware('permission:clinica.tratamientos.editar');
});

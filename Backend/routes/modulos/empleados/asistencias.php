<?php 

use App\Http\Controllers\Api\Empleados\Empleados\AsistenciasController;

// Asistencia
    Route::get('/empleados/asistencias',                 [AsistenciasController::class, 'index']);
    Route::get('/empleados/asistencias/buscar/{text}',   [AsistenciasController::class, 'search']);
    Route::post('/empleados/asistencias/filtrar',         [AsistenciasController::class, 'filter']);
    Route::post('/empleado/asistencia',                 [AsistenciasController::class, 'store']);
    Route::get('/empleado/asistencia/{id}',             [AsistenciasController::class, 'read']);
    Route::delete('/empleado/asistencia/{id}',          [AsistenciasController::class, 'delete']);

    Route::get('/empleados/asistencia-diaria', [AsistenciasController::class, 'asistenciaDiaria']);
    Route::get('/empleados/asistencia-mensual', [AsistenciasController::class, 'asistenciaMensual']);

    // Route::get('/empleados/asistencia',          [AsistenciasController::class, 'empleados']);

<?php 

use App\Http\Controllers\Api\Empleados\Planillas\PlanillasController;
use App\Http\Controllers\Api\Empleados\Planillas\DetallesController;


// Planilla
    Route::get('/planillas',                    [PlanillasController::class, 'index']);
    Route::get('/planillas/buscar/{text}',      [PlanillasController::class, 'search']);
    Route::post('/planillas/filtrar',           [PlanillasController::class, 'filter']);
    Route::post('/planilla',                    [PlanillasController::class, 'store']);
    Route::post('/planilla/proceso',            [PlanillasController::class, 'proceso']);
    Route::get('/planilla/{id}',                [PlanillasController::class, 'read']);
    Route::delete('/planilla/{id}',             [PlanillasController::class, 'delete']);
    Route::get('/planilla/reporte/{id}',       [PlanillasController::class, 'planilla']);
    Route::get('/planilla/boletas/{id}',       [PlanillasController::class, 'boletas']);

    Route::post('/planilla/detalle',            [DetallesController::class, 'store']);
    Route::get('/planilla/detalle/{id}',        [DetallesController::class, 'read']);
    Route::delete('/planilla/detalle/{id}',     [DetallesController::class, 'delete']);


    
?>

<?php 

use App\Http\Controllers\Api\Empleados\Empleados\EmpleadosController;
use App\Http\Controllers\Api\Empleados\Empleados\DocumentosController;
use App\Http\Controllers\Api\Empleados\Empleados\DeduccionesController;

use App\Http\Controllers\Api\Empleados\CargosController;

// Empleados 
    Route::get('/empleados',                 [EmpleadosController::class, 'index']);
    Route::get('/empleados/list',            [EmpleadosController::class, 'list']);
    Route::get('/empleados/buscar/{text}',   [EmpleadosController::class, 'search']);
    Route::post('/empleados/filtrar',         [EmpleadosController::class, 'filter']);
    Route::post('/empleado',                 [EmpleadosController::class, 'store']);
    Route::get('/empleado/{id}',             [EmpleadosController::class, 'read']);
    Route::delete('/empleado/{id}',          [EmpleadosController::class, 'delete']);
    Route::post('/empleados/ventas',          [EmpleadosController::class, 'ventas']);

    Route::get('/empleado/carnet/{id}',          [EmpleadosController::class, 'carnet']);
    Route::delete('/empleado/comisiones/{id}',  [EmpleadosController::class, 'comisiones']);


// Documentos
    Route::post('/empleado/documento',        [DocumentosController::class, 'store']);
    Route::delete('/empleado/documento/{id}', [DocumentosController::class, 'delete']);


// Cargo
    Route::get('/empleados/cargos',             [CargosController::class, 'index']);
    Route::post('/empleados/cargo',             [CargosController::class, 'store']);
    Route::delete('/empleados/cargo/{id}',         [CargosController::class, 'delete']);

// Deducciones
    Route::get('/empleados/deducciones',            [DeduccionesController::class, 'index']);
    Route::post('/empleados/deduccion',             [DeduccionesController::class, 'store']);
    Route::delete('/empleados/deduccion/{id}',      [DeduccionesController::class, 'delete']);


    
?>

<?php 

use App\Http\Controllers\Api\Empleados\Empleados\MetasController;

// Metas
    Route::get('/empleado/metas/{id}',      [MetasController::class, 'index']);
    Route::post('/empleado/meta',           [MetasController::class, 'store']);
    Route::post('/empleado/metaes/filtrar', [MetasController::class, 'filter']);
    Route::get('/empleado/meta/{id}',       [MetasController::class, 'read']);
    Route::delete('/empleado/meta/{id}',    [MetasController::class, 'delete']);
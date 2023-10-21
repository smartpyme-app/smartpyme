<?php 

use App\Http\Controllers\Api\Admin\Cocina\DepartamentosController;
use App\Http\Controllers\Api\Admin\Cocina\DetallesController;

    Route::get('/departamentos',                  [DepartamentosController::class, 'index']);
    Route::get('/departamento/{id}',              [DepartamentosController::class, 'read']);
    Route::post('/departamento',                  [DepartamentosController::class, 'store']);
    Route::delete('/departamento/{id}',              [DepartamentosController::class, 'delete']);

    Route::get('/departamento/detalle/{id}',              [DetallesController::class, 'read']);
    Route::post('/departamento/detalle',                  [DetallesController::class, 'store']);
    Route::delete('/departamento/detalle/{id}',              [DetallesController::class, 'delete']);
    

?>
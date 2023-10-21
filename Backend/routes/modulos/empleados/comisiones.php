<?php 

use App\Http\Controllers\Api\Empleados\Empleados\ComisionesController;

// Comisiones
    Route::get('/comisiones',                [ComisionesController::class, 'index']);
    Route::post('/comision',                 [ComisionesController::class, 'store']);
    Route::post('/comisiones/filtrar',       [ComisionesController::class, 'filter']);
    Route::get('/comision/{id}',             [ComisionesController::class, 'read']);
    Route::delete('/comision/{id}',          [ComisionesController::class, 'delete']);

    
?>
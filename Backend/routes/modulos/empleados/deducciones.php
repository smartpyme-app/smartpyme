<?php 

use App\Http\Controllers\Api\Empleados\DeduccionesController;


// Planilla
    Route::get('/deducciones',                    [DeduccionesController::class, 'index']);
    Route::post('/deduccion',                    [DeduccionesController::class, 'store']);
    Route::get('/deduccion/{id}',                [DeduccionesController::class, 'read']);
    Route::delete('/deduccion/{id}',             [DeduccionesController::class, 'delete']);
    
?>

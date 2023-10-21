<?php 

use App\Http\Controllers\Api\Contabilidad\PresupuestosController;

    Route::get('/presupuestos',             [PresupuestosController::class, 'index']);
    Route::post('/presupuesto',             [PresupuestosController::class, 'store']);
    Route::get('/presupuesto/{id}',         [PresupuestosController::class, 'read']);
    Route::post('/presupuestos/filtrar',    [PresupuestosController::class, 'filter']);
    Route::delete('/presupuesto/{id}',      [PresupuestosController::class, 'delete']);


?>

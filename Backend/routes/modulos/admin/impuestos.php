<?php 

use App\Http\Controllers\Api\Admin\ImpuestosController;
use App\Http\Controllers\Api\Admin\RetencionesController;

    Route::get('/impuestos',                  [ImpuestosController::class, 'index']);
    Route::get('/impuesto/{id}',              [ImpuestosController::class, 'read']);
    Route::post('/impuesto',                  [ImpuestosController::class, 'store']);
    Route::delete('/impuesto/{id}',           [ImpuestosController::class, 'delete']);

    Route::get('/retenciones',                 [RetencionesController::class, 'index']);
    Route::get('/retencion/{id}',              [RetencionesController::class, 'read']);
    Route::post('/retencion',                  [RetencionesController::class, 'store']);
    Route::delete('/retencion/{id}',           [RetencionesController::class, 'delete']);
    

?>

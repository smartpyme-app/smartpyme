<?php 

use App\Http\Controllers\Api\Inventario\ServiciosController;

    Route::get('/servicios',                    [ServiciosController::class, 'index']);
    Route::get('/servicio/{id}',                [ServiciosController::class, 'read']);
    Route::get('/servicios/list',                [ServiciosController::class, 'list']);
    Route::post('/servicio',                    [ServiciosController::class, 'store']);
    Route::delete('/servicio/{id}',             [ServiciosController::class, 'delete']);

    Route::get('/servicios/buscar-codigo/{codigo}', [ServiciosController::class, 'porCodigo']);

    Route::post('/servicios/analisis',          [ServiciosController::class, 'analisis']);
    Route::get('/servicio/precios/historicos/{id}', [ServiciosController::class, 'precios']);


?>

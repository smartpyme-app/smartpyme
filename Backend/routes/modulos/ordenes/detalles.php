<?php 

use App\Http\Controllers\Api\Ordenes\DetallesController;

    Route::get('/orden/detalles',           [DetallesController::class, 'index']);
    Route::get('/orden/detalle/{id}',       [DetallesController::class, 'read']);
    Route::post('/orden/detalle',           [DetallesController::class, 'store']);
    Route::delete('/orden/detalle/{id}',    [DetallesController::class, 'delete']);
    Route::post('/ordens/detalle',          [DetallesController::class, 'historial']);
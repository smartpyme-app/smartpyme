<?php 

use App\Http\Controllers\Api\Inventario\PaquetesController;

    Route::get('/paquetes',              [PaquetesController::class, 'index']);
    Route::post('/paquete',              [PaquetesController::class, 'store']);
    Route::get('/paquete/{id}',          [PaquetesController::class, 'read']);
    Route::delete('/paquete/{id}',       [PaquetesController::class, 'delete']);
    Route::get('/paquetes/exportar',          [PaquetesController::class, 'export']);


?>

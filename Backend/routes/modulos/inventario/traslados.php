<?php 
use App\Http\Controllers\Api\Inventario\TrasladosController;

    Route::get('/traslados',                 [TrasladosController::class, 'index']);
    Route::get('/traslado/{id}',             [TrasladosController::class, 'read']);
    Route::post('/traslado',                 [TrasladosController::class, 'store']);
    Route::delete('/traslado/{id}',          [TrasladosController::class, 'delete']);
    Route::get('/traslados/exportar',        [TrasladosController::class, 'export']);

?>

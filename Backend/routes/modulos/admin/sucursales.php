<?php 

use App\Http\Controllers\Api\Admin\SucursalesController;

    Route::get('/sucursales',               [SucursalesController::class, 'index']);
    Route::get('/sucursales/list',          [SucursalesController::class, 'list']);
    Route::post('/sucursal',                [SucursalesController::class, 'store'])->middleware('limite.sucursales');
    Route::get('/sucursal/{id}',            [SucursalesController::class, 'read']);
    Route::delete('/sucursal/{id}',         [SucursalesController::class, 'delete']);
    Route::get('/marcas/list',              [SucursalesController::class, 'listaMarcas']);


?>

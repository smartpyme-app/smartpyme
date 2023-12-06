<?php 

use App\Http\Controllers\Api\Admin\EmpresasController;
use App\Http\Controllers\Api\Admin\SucursalesController;
use App\Http\Controllers\Api\Admin\ReportesController;
use App\Http\Controllers\Api\Admin\CanalesController;

    Route::get('/empresas',        	        [EmpresasController::class, 'index']);
    Route::post('/empresa',                 [EmpresasController::class, 'store']);
    Route::get('/empresa/{id}',             [EmpresasController::class, 'read']);

    Route::get('/suscripcion',             [EmpresasController::class, 'suscripcion']);

    Route::get('/sucursales',               [SucursalesController::class, 'index']);
    Route::post('/sucursal',                [SucursalesController::class, 'store']);
    Route::get('/sucursal/{id}',            [SucursalesController::class, 'read']);
    Route::delete('/sucursal/{id}',         [SucursalesController::class, 'delete']);

    Route::post('/reporte/requisicion-compras',    [ReportesController::class, 'requisicionCompra']);
    Route::get('/reporte/corte/{id}',              [ReportesController::class, 'corte']);


    Route::get('/canales',               [CanalesController::class, 'index']);
    Route::post('/canal',                [CanalesController::class, 'store']);
    Route::get('/canal/{id}',            [CanalesController::class, 'read']);
    Route::delete('/canal/{id}',         [CanalesController::class, 'delete']);


?>

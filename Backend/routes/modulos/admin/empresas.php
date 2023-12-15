<?php 

use App\Http\Controllers\Api\Admin\EmpresasController;
use App\Http\Controllers\Api\Admin\SucursalesController;
use App\Http\Controllers\Api\Admin\DashboardsController;
use App\Http\Controllers\Api\Admin\ReportesController;
use App\Http\Controllers\Api\Admin\CanalesController;

    Route::get('/empresas',        	        [EmpresasController::class, 'index'])->middleware('superadmin');
    Route::get('/empresas/list',            [EmpresasController::class, 'list'])->middleware('superadmin');
    Route::post('/empresa',                 [EmpresasController::class, 'store']);
    Route::get('/empresa/{id}',             [EmpresasController::class, 'read']);

    Route::post('/empresa/eliminar/datos',   [EmpresasController::class, 'eliminarDatos'])->middleware('admin');

    Route::get('/suscripcion',             [EmpresasController::class, 'suscripcion']);

    Route::get('/sucursales',               [SucursalesController::class, 'index']);
    Route::get('/sucursales/list',               [SucursalesController::class, 'list']);
    Route::post('/sucursal',                [SucursalesController::class, 'store'])->middleware('limite.sucursales');
    Route::get('/sucursal/{id}',            [SucursalesController::class, 'read']);
    Route::delete('/sucursal/{id}',         [SucursalesController::class, 'delete']);

    Route::get('/dashboards',                 [DashboardsController::class, 'index']);
    Route::get('/dashboards/list',                 [DashboardsController::class, 'list']);
    Route::post('/dashboard',                 [DashboardsController::class, 'store']);
    Route::get('/dashboard/{id}',             [DashboardsController::class, 'read']);
    Route::delete('/dashboard/{id}',             [DashboardsController::class, 'delete']);
    
    Route::post('/reporte/requisicion-compras',    [ReportesController::class, 'requisicionCompra']);
    Route::get('/reporte/corte/{id}',              [ReportesController::class, 'corte']);


    Route::get('/canales',               [CanalesController::class, 'index']);
    Route::post('/canal',                [CanalesController::class, 'store']);
    Route::get('/canal/{id}',            [CanalesController::class, 'read']);
    Route::delete('/canal/{id}',         [CanalesController::class, 'delete']);


?>

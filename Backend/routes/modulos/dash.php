<?php 

use App\Http\Controllers\Api\DashController;
use App\Http\Controllers\Api\Inventario\ProductosController;
use App\Http\Controllers\Api\Ventas\Ordenes\OrdenesController;

    
    Route::get('/dash',                        [DashController::class, 'index']);
    Route::get('/admin',                       [DashController::class, 'admin']);
    Route::get('/galonajes',                   [DashController::class, 'galonaje']);
    Route::get('/estadistica',                 [DashController::class, 'estadistica']);
    Route::get('/telefonia-datos',             [DashController::class, 'telefoniaDatos']);

    Route::get('/dash/cocinero',                [DashController::class, 'cocinero']);
    Route::get('/dash/cocinero/departamento/{id}',  [DashController::class, 'cocineroDepartamento']);
    Route::get('/dash/mesero',                  [DashController::class, 'mesero']);
    
    Route::get('/dash/vendedor',                [DashController::class, 'vendedor']);
    Route::get('/dash/vendedor/productos',                [ProductosController::class, 'vendedor']);
    Route::get('/dash/vendedor/productos/buscar/{txt}',   [ProductosController::class, 'vendedorBuscador']);

    Route::get('/dash/vendedor/ordenes',                [OrdenesController::class, 'vendedor']);
    Route::get('/dash/vendedor/ordenes/buscar/{txt}',   [OrdenesController::class, 'vendedorBuscador']);

    Route::get('/dash/cajero/{id}',             [DashController::class, 'cajero']);

    Route::get('/barcode/{codigo}',             [DashController::class, 'barcode']);


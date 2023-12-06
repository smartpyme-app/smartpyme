<?php 

use App\Http\Controllers\Api\DashController;
use App\Http\Controllers\Api\Inventario\ProductosController;
use App\Http\Controllers\Api\Ventas\Cotizaciones\CotizacionesController;

    
    Route::get('/dash',                        [DashController::class, 'index']);
    Route::get('/admin',                       [DashController::class, 'admin']);
    
    Route::get('/corte',         [DashController::class, 'corte']);
    Route::get('/corte/documento/{id_sucursal?}/{fecha?}', [DashController::class, 'cortePdf'])->name('corte');

    Route::get('/dash/vendedor',                [DashController::class, 'vendedor']);
    Route::get('/dash/vendedor/productos',                [ProductosController::class, 'vendedor']);
    Route::get('/dash/vendedor/productos/buscar/{txt}',   [ProductosController::class, 'vendedorBuscador']);

    Route::get('/dash/vendedor/Cotizaciones',                [CotizacionesController::class, 'vendedor']);
    Route::get('/dash/vendedor/Cotizaciones/buscar/{txt}',   [CotizacionesController::class, 'vendedorBuscador']);

    Route::get('/dash/cajero/{id}',             [DashController::class, 'cajero']);

    Route::get('/barcode/{codigo}',             [DashController::class, 'barcode']);


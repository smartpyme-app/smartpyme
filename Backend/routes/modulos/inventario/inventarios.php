<?php
	
use App\Http\Controllers\Api\Inventario\InventariosController;
use Illuminate\Support\Facades\Route;

    Route::get('/inventario/{id}',                 	[InventariosController::class, 'index']);
    Route::get('/inventario/{id}',                  [InventariosController::class, 'read']);
	Route::get('/inventario/filtrar',               [InventariosController::class, 'filter']);
    Route::post('/inventario',                 		[InventariosController::class, 'store']);
    Route::delete('/inventario/{id}',               [InventariosController::class, 'delete']);
    
    Route::get('/inventario/buscar/{txt}',          [InventariosController::class, 'inventarioSearch']);
    Route::get('/inventario/sala-venta/buscar/{txt}', [InventariosController::class, 'ventaSearch']);

    Route::get('/bodega/productos/{id}',          [InventariosController::class, 'productos']);
    Route::get('/bodega/productos/buscar/{id}/{txt}',          [InventariosController::class, 'search']);
    Route::post('/bodega/productos/filtrar',       [InventariosController::class, 'productosFiltrar']);

    Route::get('/inventarios/exportar',              [InventariosController::class, 'export']);

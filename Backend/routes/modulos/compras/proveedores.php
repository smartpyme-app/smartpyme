<?php 
use App\Http\Controllers\Api\Compras\Proveedores\ProveedoresController;
use App\Http\Controllers\Api\Compras\ComprasController;

    Route::get('/proveedores',              [ProveedoresController::class, 'index']);
    Route::get('/proveedores/list',         [ProveedoresController::class, 'list']);
    Route::get('/proveedores/buscar/{txt}', [ProveedoresController::class, 'search']);
    Route::post('/proveedores/filtrar',     [ProveedoresController::class, 'filter']);
    Route::post('/proveedor',               [ProveedoresController::class, 'store']);
    Route::get('/proveedor/{id}',           [ProveedoresController::class, 'read']);
    Route::delete('/proveedor/{id}',        [ProveedoresController::class, 'delete']);

    Route::get('/proveedor/compras/{id}',       [ProveedoresController::class, 'compras']);
    Route::post('/proveedor/compras/filtrar',   [ProveedoresController::class, 'comprasFilter']);

    // Route::get('/cuentas-pagar',                  [ProveedoresController::class, 'cxp']);
    // Route::get('/cuentas-pagar/buscar/{text}',    [ProveedoresController::class, 'cxpBuscar']);

    Route::get('/cuentas-pagar',                  [ComprasController::class, 'cxp']);
    Route::get('/cuentas-pagar/buscar/{text}',    [ComprasController::class, 'cxpBuscar']);

    Route::post('/proveedores-personas/importar',          [ProveedoresController::class, 'importPersonas']);
    Route::post('/proveedores-empresas/importar',          [ProveedoresController::class, 'importEmpresas']);
    Route::get('/proveedores-personas/exportar',    [ProveedoresController::class, 'exportPersonas']);
    Route::get('/proveedores-empresas/exportar',    [ProveedoresController::class, 'exportEmpresas']);


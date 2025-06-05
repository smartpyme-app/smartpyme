<?php 

use App\Http\Controllers\Api\Compras\Gastos\GastosController;
use App\Http\Controllers\Api\Compras\Gastos\CategoriasController;
use App\Http\Controllers\Api\Inventario\ProveedorController;

    Route::get('/gastos',             [GastosController::class, 'index']);
    Route::post('/gasto',             [GastosController::class, 'store']);
    Route::get('/gasto/{id}',         [GastosController::class, 'read']);
    Route::post('/gastos/filtrar',    [GastosController::class, 'filter']);
    Route::delete('/gasto/{id}',      [GastosController::class, 'delete']);
    Route::get('/gastos/exportar',    [GastosController::class, 'export']);


    Route::post('/gastos/dash',         [GastosController::class, 'dash']);


    Route::get('/gastos/categorias',    [CategoriasController::class, 'index']);
    Route::post('/gastos/categoria',    [CategoriasController::class, 'store']);
    Route::delete('/gastos/categoria/{id}', [CategoriasController::class, 'delete']);

    Route::post('/gastos/importar-json', [GastosController::class, 'importarJson']);
    Route::post('/proveedores/buscar-nit', [ProveedorController::class, 'buscarPorNit']);
    Route::get('/gastos/nums-ids', [GastosController::class, 'getNumerosIdentificacion']);
    
?>

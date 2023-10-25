<?php 


use App\Http\Controllers\Api\Inventario\Categorias\CategoriasController;
use App\Http\Controllers\Api\Inventario\Categorias\SubCategoriasController;
use App\Http\Controllers\Api\Inventario\Categorias\TiposController;

    Route::get('/categorias',               [CategoriasController::class, 'index']);
    Route::get('/categoria/{id}',           [CategoriasController::class, 'read']);
    Route::get('/categorias/buscar/{text}', [CategoriasController::class, 'search']);
    Route::post('/categorias/filtrar',      [CategoriasController::class, 'filter']);
    Route::post('/categoria',               [CategoriasController::class, 'store']);
    Route::delete('/categoria/{id}',        [CategoriasController::class, 'delete']);

    Route::post('/categorias/ventas',       [CategoriasController::class, 'historialVentas']);
    Route::post('/categorias/compras',       [CategoriasController::class, 'historialCompras']);

    Route::post('/categorias/import',          [CategoriasController::class, 'import']);


    Route::get('subcategorias',             [SubCategoriasController::class, 'index']);
    Route::get('/subcategoria/{id}',           [SubCategoriasController::class, 'read']);
    Route::get('/subcategorias/buscar/{text}', [SubCategoriasController::class, 'search']);
    Route::post('/subcategoria',               [SubCategoriasController::class, 'store']);
    Route::delete('/subcategoria/{id}',        [SubCategoriasController::class, 'delete']);

    Route::post('/subcategoria/cambio',               [SubCategoriasController::class, 'change']);

    Route::get('/tipos',                    [TiposController::class, 'index']);
    Route::get('/subcategoria/{id}/tipos', [TiposController::class, 'bySubcategoria']);
    Route::get('/tipo/{id}',           [TiposController::class, 'read']);
    Route::get('/tipos/buscar/{text}', [TiposController::class, 'search']);
    Route::post('/tipo',               [TiposController::class, 'store']);
    Route::delete('/tipo/{id}',        [TiposController::class, 'delete']);

    Route::post('/subcategorias/import',          [SubCategoriasController::class, 'import']);


?>

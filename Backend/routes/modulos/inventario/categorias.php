<?php 


use App\Http\Controllers\Api\Inventario\Categorias\CategoriasController;
use App\Http\Controllers\Api\Inventario\Categorias\SubCategoriasController;

    Route::get('/categorias',               [CategoriasController::class, 'index']);
    Route::get('/categorias/list',          [CategoriasController::class, 'list']);
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

    Route::post('/subcategorias/import',          [SubCategoriasController::class, 'import']);


?>

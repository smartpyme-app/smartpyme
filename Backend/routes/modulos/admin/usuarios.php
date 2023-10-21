<?php 

use App\Http\Controllers\Api\Admin\UsuariosController;
use App\Http\Controllers\Api\Admin\UsuariosMetaController;

    Route::get('/usuarios',                 [UsuariosController::class, 'index']);
    Route::get('/usuarios/list',            [UsuariosController::class, 'list']);
    Route::get('/usuarios/buscar/{text}',   [UsuariosController::class, 'search']);
    Route::post('/usuarios/filtrar',        [UsuariosController::class, 'filter']);
    Route::post('/usuario',                 [UsuariosController::class, 'store']);
    Route::get('/usuario/{id}',             [UsuariosController::class, 'read']);
    Route::delete('/usuario/{id}',          [UsuariosController::class, 'delete']);

    Route::post('/usuario/meta',            [UsuariosMetaController::class, 'store']);
    Route::get('/usuario/metas/{id}',       [UsuariosMetaController::class, 'read']);

    Route::get('/usuarios/caja/{id}',       [UsuariosController::class, 'caja']);

    Route::post('/usuario-validar',       [UsuariosController::class, 'validar']);
    Route::post('/usuario-auth',       [UsuariosController::class, 'auth']);

?>

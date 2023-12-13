<?php 

use App\Http\Controllers\Api\SuperAdmin\UsuariosController;

    Route::get('/admin-usuarios',                 [UsuariosController::class, 'index']);
    Route::get('/admin-usuarios/list',            [UsuariosController::class, 'list']);
    Route::post('/admin-usuario',                 [UsuariosController::class, 'store']);
    Route::get('/admin-usuario/{id}',             [UsuariosController::class, 'read']);
    Route::delete('/admin-usuario/{id}',          [UsuariosController::class, 'delete']);

?>

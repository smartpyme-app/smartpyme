<?php 

use App\Http\Controllers\Api\Inventario\MateriaPrimaController;

    Route::get('/materias-primas',                    [MateriaPrimaController::class, 'index']);
    Route::get('/materia-prima/{id}',                 [MateriaPrimaController::class, 'read']);
    Route::get('/materias-primas/list',               [MateriaPrimaController::class, 'list']);
    Route::get('/materias-primas/buscar/{text}',      [MateriaPrimaController::class, 'search']);
    Route::post('/materias-primas/filtrar',           [MateriaPrimaController::class, 'filter']);
    Route::post('/materia-prima',                     [MateriaPrimaController::class, 'store']);
    Route::delete('/materia-prima/{id}',              [MateriaPrimaController::class, 'delete']);


?>
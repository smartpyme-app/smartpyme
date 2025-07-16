<?php 

use App\Http\Controllers\Api\Contabilidad\ProyectosController;
use Illuminate\Support\Facades\Route;

    Route::get('/proyectos',             [ProyectosController::class, 'index']);
    Route::get('/proyectos/list',        [ProyectosController::class, 'list']);
    Route::post('/proyecto',             [ProyectosController::class, 'store']);
    Route::get('/proyecto/{id}',         [ProyectosController::class, 'read']);
    Route::post('/proyectos/filtrar',    [ProyectosController::class, 'filter']);
    Route::delete('/proyecto/{id}',      [ProyectosController::class, 'delete']);


?>

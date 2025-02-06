<?php 

use App\Http\Controllers\Api\Admin\AccesosController;

    Route::get('/accesos',                  [AccesosController::class, 'index']);
    Route::get('/acceso/{id}',              [AccesosController::class, 'read']);
    Route::post('/acceso',                  [AccesosController::class, 'store']);
    Route::delete('/acceso/{id}',           [AccesosController::class, 'delete']);

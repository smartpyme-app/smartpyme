<?php 

use App\Http\Controllers\Api\Admin\SuscripcionesController;
use Illuminate\Support\Facades\Route;

    Route::get('/suscripciones',               [SuscripcionesController::class, 'index']);
    Route::get('/suscripciones/list',          [SuscripcionesController::class, 'list']);
    Route::post('/suscripcion',                [SuscripcionesController::class, 'store'])->middleware('limite.suscripciones');
    Route::get('/suscripcion/{id}',            [SuscripcionesController::class, 'read']);
    Route::delete('/suscripcion/{id}',         [SuscripcionesController::class, 'delete']);


?>

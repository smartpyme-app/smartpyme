<?php

use App\Http\Controllers\Api\SuperAdmin\AliadosController;

Route::get('/aliados', [AliadosController::class, 'index']);
Route::post('/aliado', [AliadosController::class, 'store']);
Route::delete('/aliado/{id}', [AliadosController::class, 'delete']);

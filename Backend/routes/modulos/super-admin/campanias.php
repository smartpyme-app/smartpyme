<?php

use App\Http\Controllers\Api\SuperAdmin\CampaniasController;

Route::get('/campanias', [CampaniasController::class, 'index']);
Route::post('/campania', [CampaniasController::class, 'store']);
Route::delete('/campania/{id}', [CampaniasController::class, 'delete']);

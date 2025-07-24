<?php

use App\Http\Controllers\Api\Inventario\CustomFieldsController;
use Illuminate\Support\Facades\Route;

Route::get('/custom-fields', [CustomFieldsController::class, 'index']);
Route::post('/custom-fields', [CustomFieldsController::class, 'store']);
Route::put('/custom-fields/{id}', [CustomFieldsController::class, 'update']);
Route::get('/custom-fields/{id}/usage', [CustomFieldsController::class, 'usage']);
Route::delete('/custom-fields/{id}', [CustomFieldsController::class, 'destroy']);


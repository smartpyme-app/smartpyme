<?php

use App\Http\Controllers\Api\Admin\MonedaConfigController;
use Illuminate\Support\Facades\Route;

Route::get('/moneda/config', [MonedaConfigController::class, 'config']);
Route::get('/moneda/tipo-cambio', [MonedaConfigController::class, 'preview']);

<?php

use App\Http\Controllers\Api\TokenClientController;
//use Route;
use Illuminate\Support\Facades\Route;


Route::post('/token/client', [TokenClientController::class, 'issueToken'])
    ->middleware(['create_token', 'throttle'])
    ->name('token');
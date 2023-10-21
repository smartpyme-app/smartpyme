<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Auth\AuthJWTController;

Route::post('/login',    [AuthJWTController::class, 'login']);
Route::post('/register', [AuthJWTController::class, 'register']);
Route::post('/logout', [AuthJWTController::class, 'logout']);

?>
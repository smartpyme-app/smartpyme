<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Auth\AuthJWTController;
use App\Http\Controllers\Api\PromocionalesController;

Route::post('/login',    [AuthJWTController::class, 'login']);
Route::post('/logout', [AuthJWTController::class, 'logout']);

Route::post('password/email', [AuthJWTController::class, 'sendResetLinkEmail']);

Route::post('/register', [AuthJWTController::class, 'register']);

Route::post('/cancelar-suscripcion', [AuthJWTController::class, 'cancelarSuscripcion']);

Route::get('/me/{id}', [AuthJWTController::class, 'me']);

// Códigos promocionales (público, sin autenticación)
Route::post('/promocional/validar', [PromocionalesController::class, 'validar']);

?>

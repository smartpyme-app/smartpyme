<?php

use App\Http\Controllers\Auth\AuthJWTController;
use App\Http\Controllers\WebhookN1coController;
use App\Http\Controllers\n1co\N1coChargeController;
use App\Http\Controllers\n1co\EstadoController;
use Illuminate\Support\Facades\Route;

Route::get('/paises-suscripcion', [EstadoController::class, 'paisesSuscripcion']);
Route::get('/estados-por-pais/{pais_id}', [EstadoController::class, 'getEstadosByPais']);

Route::group(['prefix' => 'payment'], function () {
	Route::post('method', [N1coChargeController::class, 'createPaymentMethod']);
	Route::post('process', [N1coChargeController::class, 'processCharge']);
	Route::post('process-ready', [N1coChargeController::class, 'processChargeReady']);
	Route::post('process/3ds', [N1coChargeController::class, 'processCharge3DS']);
	Route::post('update-method-payment', [N1coChargeController::class, 'updateMethodPayment']);
	Route::post('check-auth-status', [N1coChargeController::class, 'checkAuthenticationStatus']);
	Route::get('validate/{paymentId}', [N1coChargeController::class, 'validatePayment']);
	Route::get('{empresaId}', [N1coChargeController::class, 'checkout']);

});
?>
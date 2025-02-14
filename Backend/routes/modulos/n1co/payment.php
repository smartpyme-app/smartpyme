<?php

use App\Http\Controllers\Auth\AuthJWTController;
use App\Http\Controllers\n1co\N1coChargeController;
use App\Http\Controllers\WebhookN1coController;
use Illuminate\Support\Facades\Route;


Route::group([
    'prefix' => 'payment',
    'middleware' => ['cors']
], function () {
    Route::options('method', function() {
        return response()->json([], 200);
    });
    
    Route::post('method', 'N1coChargeController@createPaymentMethod');
    Route::post('process', 'N1coChargeController@processCharge');
    Route::get('validate/{paymentId}', 'N1coChargeController@validatePayment');
    Route::get('{empresaId}', 'N1coChargeController@checkout');
});

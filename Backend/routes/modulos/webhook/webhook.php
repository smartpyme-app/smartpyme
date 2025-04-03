<?php

use App\Http\Controllers\Api\Webhook\WooCommerceController;
//use Route;
use Illuminate\Support\Facades\Route;

//Route::post('/webhook/woocommerce', [WooCommerceController::class, 'procesarVenta']);
Route::post('/webhook/woocommerce/{token}', [WooCommerceController::class, 'procesarVenta']);
//Route::post('/webhook/woocommerce', [WooCommerceController::class, 'saveCredentials']);
//ventas/externas
Route::post('/ventas-externas', [WooCommerceController::class, 'ventas']) ->middleware('token_client', 'CheckClientTokenAccess', 'client');

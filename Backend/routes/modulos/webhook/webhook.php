<?php

use App\Http\Controllers\Api\Webhook\WooCommerceController;
//use Route;
use Illuminate\Support\Facades\Route;

Route::post('/webhook/woocommerce', [WooCommerceController::class, 'procesarVenta']);

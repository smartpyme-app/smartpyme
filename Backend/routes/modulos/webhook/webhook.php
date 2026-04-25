<?php

use App\Http\Controllers\Api\Webhook\WooCommerceController;
use App\Http\Controllers\Api\Webhook\ShopifyController;
//use Route;
use Illuminate\Support\Facades\Route;

//Route::post('/webhook/woocommerce', [WooCommerceController::class, 'procesarVenta']);
Route::post('/webhook/woocommerce/{token}', [WooCommerceController::class, 'procesarVenta']);
Route::post('/webhook/woocommerce/{token}/producto', [WooCommerceController::class, 'procesarProductoWooCommerce']);
//Route::post('/webhook/woocommerce', [WooCommerceController::class, 'saveCredentials']);


// En routes/api.php o routes/web.php
Route::post('/webhook/shopify/{token}', [ShopifyController::class, 'handle'])
    ->name('shopify.webhook.orders');

Route::post('/shopify/exportar', [ShopifyController::class, 'exportarShopify'])
    ->middleware('auth')
    ->name('shopify.exportar');
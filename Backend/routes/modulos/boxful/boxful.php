<?php

use App\Http\Controllers\BoxFul\BoxFulController;
use App\Http\Controllers\BoxFul\BoxFulAddressController;
use App\Http\Controllers\BoxFul\BoxFulShippingController;
use Illuminate\Support\Facades\Route;

Route::get('clientes/{cliente}/direcciones-envio', [BoxFulShippingController::class, 'getClientAddresses']);
Route::post('clientes/{cliente}/direcciones-envio', [BoxFulShippingController::class, 'storeClientAddress']);

Route::prefix('boxful')->group(function () {
    Route::get('test-connection', [BoxFulController::class, 'testConnection']);
    Route::post('configurar-origen', [BoxFulController::class, 'configurarOrigen']);
    Route::post('registrar-webhook', [BoxFulController::class, 'registrarWebhook']);
    Route::post('client-webhook', [BoxFulController::class, 'storeClientWebhook']);

    // Estados y Ciudades
    Route::get('states', [BoxFulAddressController::class, 'getStates']);

    // CRUD de Direcciones
    Route::get('addresses', [BoxFulAddressController::class, 'getAddresses']);
    Route::get('addresses/{id}', [BoxFulAddressController::class, 'showAddress']);
    Route::post('addresses', [BoxFulAddressController::class, 'storeAddress']);
    Route::patch('addresses/{id}', [BoxFulAddressController::class, 'updateAddress']);
    Route::delete('addresses/{id}', [BoxFulAddressController::class, 'destroyAddress']);

    // Courier y Shipment Proxies
    Route::post('courier/available', [BoxFulShippingController::class, 'getCouriersAvailable']);
    Route::post('locker/available', [BoxFulShippingController::class, 'getAvailableLockers']);
    Route::get('courier/shiphero', [BoxFulShippingController::class, 'getShipheroCouriers']);
    Route::post('quoter', [BoxFulShippingController::class, 'getQuote']);
    Route::post('shiphero/orders', [BoxFulShippingController::class, 'createShipheroOrder']);
    Route::post('shiphero/orders/search', [BoxFulShippingController::class, 'searchShipheroOrders']);
    Route::get('shiphero/products', [BoxFulShippingController::class, 'getShipheroProducts']);
    Route::post('shipment', [BoxFulShippingController::class, 'createShipment']);
    Route::get('shipment/{id}', [BoxFulShippingController::class, 'getShipment']);
    Route::get('tracking/{id}', [BoxFulShippingController::class, 'getTracking']);
});


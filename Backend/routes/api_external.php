<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\External\SalesController;
use App\Http\Controllers\Api\External\InventoryController;
use App\Http\Controllers\Api\External\SystemController;
use App\Http\Controllers\Api\External\ReturnsController;

/*
|--------------------------------------------------------------------------
| External API Routes
|--------------------------------------------------------------------------
|
| Rutas para el API externo que permite a proveedores terceros acceder
| a datos de ventas e inventario usando woocommerce_api_key
|
*/

Route::prefix('external/v1')->middleware(['external.api'])->group(function () {
    
    // Rutas del sistema
    Route::prefix('system')->group(function () {
        Route::get('/rate-limit', [SystemController::class, 'rateLimitStatus'])->name('external.system.rate-limit');
    });
    
    // Rutas de ventas
    Route::prefix('sales')->group(function () {
        Route::get('/', [SalesController::class, 'index'])->name('external.sales.index');
        Route::get('/summary', [SalesController::class, 'summary'])->name('external.sales.summary');
        Route::get('/{id}', [SalesController::class, 'show'])->name('external.sales.show');
    });
    
    // Rutas de inventario
    Route::prefix('inventory')->group(function () {
        Route::get('/', [InventoryController::class, 'index'])->name('external.inventory.index');
        Route::get('/summary', [InventoryController::class, 'summary'])->name('external.inventory.summary');
        Route::get('/{id}', [InventoryController::class, 'show'])->name('external.inventory.show');
    });
    
    // Rutas de devoluciones
    Route::prefix('returns')->group(function () {
        Route::get('/', [ReturnsController::class, 'index'])->name('external.returns.index');
        Route::get('/summary', [ReturnsController::class, 'summary'])->name('external.returns.summary');
        Route::get('/{id}', [ReturnsController::class, 'show'])->name('external.returns.show');
    });
});

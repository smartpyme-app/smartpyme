<?php

use App\Http\Controllers\Api\Ventas\Creditos\CreditosClientesController;
use Illuminate\Support\Facades\Route;

Route::middleware(['verificar.funcionalidad:creditos-clientes'])->group(function () {
    Route::get('creditos-clientes', [CreditosClientesController::class, 'index']);
    Route::get('creditos-clientes/cola', [CreditosClientesController::class, 'cola']);
    Route::get('creditos-clientes/por-venta/{idVenta}', [CreditosClientesController::class, 'porVenta']);
    Route::get('creditos-clientes/cuotas/{id}/prefill', [CreditosClientesController::class, 'prefill']);
    Route::get('creditos-clientes/{id}', [CreditosClientesController::class, 'show']);
    Route::post('creditos-clientes', [CreditosClientesController::class, 'store']);
});

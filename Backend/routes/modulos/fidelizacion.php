<?php

use App\Http\Controllers\Api\FidelizacionClientes\TipoClienteEmpresaController;

// Rutas para Fidelización de Clientes
Route::prefix('fidelizacion')->group(function () {
    
    // Tipos de Cliente Empresa
    Route::prefix('tipos-cliente')->group(function () {
        Route::get('/', [TipoClienteEmpresaController::class, 'index']);
        Route::get('/tipos-base', [TipoClienteEmpresaController::class, 'getTiposBase']);
        Route::post('/', [TipoClienteEmpresaController::class, 'store']);
        Route::put('/{id}', [TipoClienteEmpresaController::class, 'update']);
        Route::delete('/{id}', [TipoClienteEmpresaController::class, 'destroy']);
        Route::patch('/{id}/toggle-status', [TipoClienteEmpresaController::class, 'toggleStatus']);
    });
    
});

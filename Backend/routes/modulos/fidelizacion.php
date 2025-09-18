<?php

use App\Http\Controllers\Api\FidelizacionClientes\TipoClienteEmpresaController;
use App\Http\Controllers\Api\FidelizacionClientes\ClienteFidelizacionController;
use Illuminate\Support\Facades\Route;

// Rutas para Lealtad de Clientes
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
    
    // Clientes con Lealtad
    Route::prefix('clientes')->group(function () {
        Route::get('/', [ClienteFidelizacionController::class, 'index']);
        Route::get('/tipo/{tipoId}', [ClienteFidelizacionController::class, 'getByTipo']);
        Route::get('/{id}/detalles', [ClienteFidelizacionController::class, 'getDetalles']);
        Route::patch('/{id}/cambiar-tipo', [ClienteFidelizacionController::class, 'cambiarTipo']);
        Route::get('/{id}/historial-puntos', [ClienteFidelizacionController::class, 'getHistorialPuntos']);
        Route::get('/{id}/beneficios-disponibles', [ClienteFidelizacionController::class, 'getBeneficiosDisponibles']);
        Route::post('/{id}/canjear-puntos', [ClienteFidelizacionController::class, 'canjearPuntos']);
        Route::get('/exportar', [ClienteFidelizacionController::class, 'exportar']);
    });
    
});

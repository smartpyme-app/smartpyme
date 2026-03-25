<?php

use App\Http\Controllers\Api\Restaurante\MesaController;
use App\Http\Controllers\Api\Restaurante\SesionMesaController;
use App\Http\Controllers\Api\Restaurante\OrdenDetalleController;
use App\Http\Controllers\Api\Restaurante\ComandaController;
use App\Http\Controllers\Api\Restaurante\PreCuentaController;
use App\Http\Controllers\Api\Restaurante\ReservaController;
use App\Http\Controllers\Api\Restaurante\PedidoRestauranteController;
use Illuminate\Support\Facades\Route;

Route::prefix('restaurante')
    ->middleware(['verificar.funcionalidad:modulo-restaurante'])
    ->group(function () {
        // Mesas
        Route::get('/mesas', [MesaController::class, 'index']);
        Route::post('/mesas', [MesaController::class, 'store']);
        Route::get('/mesas/{id}', [MesaController::class, 'show']);
        Route::put('/mesas/{id}', [MesaController::class, 'update']);

        // Sesiones
        Route::post('/sesiones-mesa', [SesionMesaController::class, 'store']);
        Route::get('/sesiones-mesa/{id}', [SesionMesaController::class, 'show']);
        Route::put('/sesiones-mesa/{id}', [SesionMesaController::class, 'update']);
        Route::put('/sesiones-mesa/{id}/cerrar', [SesionMesaController::class, 'cerrar']);

        // Órdenes (items por sesión)
        Route::post('/sesiones-mesa/{id}/items', [OrdenDetalleController::class, 'store']);
        Route::put('/sesiones-mesa/{sesionId}/items/{itemId}', [OrdenDetalleController::class, 'update']);
        Route::delete('/sesiones-mesa/{sesionId}/items/{itemId}', [OrdenDetalleController::class, 'destroy']);

        // Comandas
        Route::get('/comandas', [ComandaController::class, 'index']);
        Route::post('/sesiones-mesa/{id}/comandas', [ComandaController::class, 'store']);
        Route::put('/comandas/{id}/estado', [ComandaController::class, 'actualizarEstado']);
        Route::get('/comandas/{id}/imprimir', [ComandaController::class, 'imprimir']);

        // Pre-cuentas
        Route::post('/sesiones-mesa/{id}/pre-cuenta', [PreCuentaController::class, 'generar']);
        Route::post('/pre-cuentas/{id}/dividir', [PreCuentaController::class, 'dividir']);
        Route::post('/pre-cuentas/{id}/facturar', [PreCuentaController::class, 'prepararFactura']);
        Route::put('/pre-cuentas/{id}/marcar-facturada', [PreCuentaController::class, 'marcarFacturada']);
        Route::get('/pre-cuentas/{id}', [PreCuentaController::class, 'show']);
        Route::get('/pre-cuentas/{id}/imprimir', [PreCuentaController::class, 'imprimir']);

        // Reservas
        Route::get('/reservas', [ReservaController::class, 'index']);
        Route::post('/reservas', [ReservaController::class, 'store']);
        Route::get('/reservas/{id}', [ReservaController::class, 'show']);
        Route::put('/reservas/{id}', [ReservaController::class, 'update']);
        Route::put('/reservas/{id}/cancelar', [ReservaController::class, 'cancelar']);
        Route::put('/reservas/{id}/convertir-sesion', [ReservaController::class, 'convertirEnSesion']);

        // Pedidos de canal (Spoties / manual; Fase 1: CRUD) — `pedidos` en API REST; no confundir con ventas
        Route::get('/pedidos', [PedidoRestauranteController::class, 'index']);
        Route::post('/pedidos', [PedidoRestauranteController::class, 'store']);
        Route::get('/pedidos/{id}', [PedidoRestauranteController::class, 'show']);
        Route::put('/pedidos/{id}', [PedidoRestauranteController::class, 'update']);
        Route::delete('/pedidos/{id}', [PedidoRestauranteController::class, 'destroy']);
    });

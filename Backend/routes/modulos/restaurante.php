<?php

use App\Http\Controllers\Api\Restaurante\MesaController;
use App\Http\Controllers\Api\Restaurante\PosMenuController;
use App\Http\Controllers\Api\Restaurante\SesionMesaController;
use App\Http\Controllers\Api\Restaurante\OrdenDetalleController;
use App\Http\Controllers\Api\Restaurante\ComandaController;
use App\Http\Controllers\Api\Restaurante\PreCuentaController;
use App\Http\Controllers\Api\Restaurante\ReservaController;
use App\Http\Controllers\Api\Restaurante\PedidoRestauranteController;
use App\Http\Controllers\Api\Restaurante\ZonaRestauranteController;
use Illuminate\Support\Facades\Route;

Route::prefix('restaurante')
    ->middleware(['verificar.funcionalidad:modulo-restaurante'])
    ->group(function () {
        // Mesas
        Route::get('/mesas', [MesaController::class, 'index'])->middleware('permission:restaurante.ver');
        Route::post('/mesas', [MesaController::class, 'store'])->middleware('permission:restaurante.crear');
        Route::get('/mesas/{id}', [MesaController::class, 'show'])->middleware('permission:restaurante.ver');
        Route::put('/mesas/{id}', [MesaController::class, 'update'])->middleware('permission:restaurante.editar');

        // Catálogo táctil (POS Menu)
        Route::get('/pos-menu/categorias', [PosMenuController::class, 'categorias'])->middleware('permission:restaurante.ver');
        Route::get('/pos-menu/categorias/{id}/contenido', [PosMenuController::class, 'contenidoCategoria'])->middleware('permission:restaurante.ver');
        Route::get('/pos-menu/subcategorias/{id}/productos', [PosMenuController::class, 'productosSubcategoria'])->middleware('permission:restaurante.ver');
        Route::get('/pos-menu/buscar', [PosMenuController::class, 'buscar'])->middleware('permission:restaurante.ver');

        // Zonas
        Route::get('/zonas', [ZonaRestauranteController::class, 'index'])->middleware('permission:restaurante.ver');
        Route::post('/zonas', [ZonaRestauranteController::class, 'store'])->middleware('permission:restaurante.crear');
        Route::get('/zonas/{id}', [ZonaRestauranteController::class, 'show'])->middleware('permission:restaurante.ver');
        Route::put('/zonas/{id}', [ZonaRestauranteController::class, 'update'])->middleware('permission:restaurante.editar');
        Route::delete('/zonas/{id}', [ZonaRestauranteController::class, 'destroy'])->middleware('permission:restaurante.eliminar');

        // Sesiones
        Route::post('/sesiones-mesa', [SesionMesaController::class, 'store'])->middleware('permission:restaurante.crear');
        Route::get('/sesiones-mesa/{id}', [SesionMesaController::class, 'show'])->middleware('permission:restaurante.ver');
        Route::put('/sesiones-mesa/{id}', [SesionMesaController::class, 'update'])->middleware('permission:restaurante.editar');
        Route::put('/sesiones-mesa/{id}/cerrar', [SesionMesaController::class, 'cerrar'])->middleware('permission:restaurante.editar');
        Route::put('/sesiones-mesa/{id}/reactivar-consumo', [SesionMesaController::class, 'reactivarConsumo'])->middleware('permission:restaurante.editar');
        Route::post('/sesiones-mesa/{id}/trasladar-items', [SesionMesaController::class, 'trasladarItems'])->middleware('permission:restaurante.editar');

        // Órdenes (items por sesión)
        Route::post('/sesiones-mesa/{id}/items', [OrdenDetalleController::class, 'store'])->middleware('permission:restaurante.crear');
        Route::put('/sesiones-mesa/{sesionId}/items/{itemId}', [OrdenDetalleController::class, 'update'])->middleware('permission:restaurante.editar');
        Route::post('/sesiones-mesa/{sesionId}/items/{itemId}/eliminar', [OrdenDetalleController::class, 'eliminar'])->middleware('permission:restaurante.eliminar');
        Route::delete('/sesiones-mesa/{sesionId}/items/{itemId}', [OrdenDetalleController::class, 'destroy'])->middleware('permission:restaurante.eliminar');

        // Comandas
        Route::get('/comandas', [ComandaController::class, 'index'])->middleware('permission:restaurante.ver');
        Route::post('/sesiones-mesa/{id}/comandas', [ComandaController::class, 'store'])->middleware('permission:restaurante.crear');
        Route::put('/comandas/{id}/estado', [ComandaController::class, 'actualizarEstado'])->middleware('permission:restaurante.editar');
        Route::get('/comandas/{id}/imprimir', [ComandaController::class, 'imprimir'])->middleware('permission:restaurante.ver');

        // Pre-cuentas
        Route::post('/sesiones-mesa/{id}/pre-cuenta', [PreCuentaController::class, 'generar'])->middleware('permission:restaurante.crear');
        Route::post('/pre-cuentas/{id}/dividir', [PreCuentaController::class, 'dividir'])->middleware('permission:restaurante.editar');
        Route::post('/pre-cuentas/{id}/facturar', [PreCuentaController::class, 'prepararFactura'])->middleware('permission:restaurante.editar');
        Route::put('/pre-cuentas/{id}/marcar-facturada', [PreCuentaController::class, 'marcarFacturada'])->middleware('permission:restaurante.editar');
        Route::get('/pre-cuentas/{id}', [PreCuentaController::class, 'show'])->middleware('permission:restaurante.ver');
        Route::get('/pre-cuentas/{id}/imprimir', [PreCuentaController::class, 'imprimir'])->middleware('permission:restaurante.ver');

        // Reservas
        Route::get('/reservas', [ReservaController::class, 'index'])->middleware('permission:restaurante.ver');
        Route::post('/reservas', [ReservaController::class, 'store'])->middleware('permission:restaurante.crear');
        Route::get('/reservas/{id}', [ReservaController::class, 'show'])->middleware('permission:restaurante.ver');
        Route::put('/reservas/{id}', [ReservaController::class, 'update'])->middleware('permission:restaurante.editar');
        Route::put('/reservas/{id}/cancelar', [ReservaController::class, 'cancelar'])->middleware('permission:restaurante.editar');
        Route::put('/reservas/{id}/convertir-sesion', [ReservaController::class, 'convertirEnSesion'])->middleware('permission:restaurante.editar');

        // Pedidos de canal (Spoties / manual) — permisos independientes de restaurante
        Route::get('/pedidos', [PedidoRestauranteController::class, 'index'])->middleware('permission:pedidos.ver');
        Route::post('/pedidos', [PedidoRestauranteController::class, 'store'])->middleware('permission:pedidos.crear');
        Route::get('/pedidos/{id}/imprimir', [PedidoRestauranteController::class, 'imprimir'])->middleware('permission:pedidos.ver');
        Route::post('/pedidos/{id}/comandas', [PedidoRestauranteController::class, 'enviarComanda'])->middleware('permission:pedidos.editar');
        Route::post('/pedidos/{id}/preparar-factura', [PedidoRestauranteController::class, 'prepararFactura'])->middleware('permission:pedidos.editar');
        Route::put('/pedidos/{id}/marcar-facturado', [PedidoRestauranteController::class, 'marcarFacturado'])->middleware('permission:pedidos.editar');
        Route::put('/pedidos/{id}/confirmar', [PedidoRestauranteController::class, 'confirmar'])->middleware('permission:pedidos.editar');
        Route::put('/pedidos/{id}/anular', [PedidoRestauranteController::class, 'anular'])->middleware('permission:pedidos.editar');
        Route::get('/pedidos/{id}', [PedidoRestauranteController::class, 'show'])->middleware('permission:pedidos.ver');
        Route::put('/pedidos/{id}', [PedidoRestauranteController::class, 'update'])->middleware('permission:pedidos.editar');
        Route::delete('/pedidos/{id}', [PedidoRestauranteController::class, 'destroy'])->middleware('permission:pedidos.eliminar');
    });

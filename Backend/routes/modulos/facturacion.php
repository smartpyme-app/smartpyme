<?php

use App\Http\Controllers\Api\Admin\CajasController;
use App\Http\Controllers\Api\Ventas\VentasController;
use App\Http\Controllers\Api\Ventas\GenerarDocumentosController;
use App\Http\Controllers\Api\Ventas\Devoluciones\DevolucionVentasController;
use App\Http\Controllers\CotizacionVentaController;
use Illuminate\Support\Facades\Route;

// Info del Dash
Route::get('/caja',                      [CajasController::class, 'caja']);
// Registro de venta
Route::post('/facturacion',              [VentasController::class, 'facturacion']);
// Generar facturas
Route::get('/reporte/facturacion/{id}',  [GenerarDocumentosController::class, 'generarDoc']);
Route::get('/reporte/devolucion/{id}',   [DevolucionVentasController::class, 'generarDoc']);
// Imprimit anulación de factura
Route::get('/reporte/anulacion',         [VentasController::class, 'anularDoc']);
// Listado de ventas
Route::get('/ventas/corte',             [VentasController::class, 'corte']);
// Registrar devolucion
Route::post('/devolucion-venta',         [DevolucionVentasController::class, 'facturacion']);
// Listado de devoluciones
Route::get('/devoluciones-ventas/corte', [DevolucionVentasController::class, 'corte']);

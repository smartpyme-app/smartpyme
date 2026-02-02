<?php

use App\Http\Controllers\Api\Ventas\VentasController;
use App\Http\Controllers\Api\Ventas\EntradasController;
use App\Http\Controllers\Api\Ventas\VentasImportController;
use App\Http\Controllers\Api\WompiController;
use Illuminate\Support\Facades\Route;

    Route::get('/ventas',               [VentasController::class, 'index']);
    Route::get('/venta/{id}',           [VentasController::class, 'read']);
    Route::post('/venta',               [VentasController::class, 'store']);
    Route::delete('/venta/{id}',        [VentasController::class, 'delete']);

    Route::post('/venta/facturacion',  [VentasController::class, 'facturacion']);
    Route::post('/venta/facturacion/consigna',  [VentasController::class, 'facturacionConsigna']);
    Route::get('/venta/facturacion/impresion/{id}',  [VentasController::class, 'generarDoc']);

    Route::get('/ventas/pendientes',       [VentasController::class, 'pendientes']);
    Route::get('/ventas/sin-devolucion',       [VentasController::class, 'sinDevolucion']);

    Route::post('/propinas',             [VentasController::class, 'propinas']);

    Route::post('/ventas/historial',    [VentasController::class, 'historial']);

    Route::get('/ventas/exportar',    [VentasController::class, 'export']);
    Route::get('/ventas-detalles/exportar',    [VentasController::class, 'exportDetalles']);

     Route::get('/ventas-detalles/exportar/diario',    [VentasController::class, 'reporteDiario']);


    Route::get('/venta/wompi-link/{id}', [WompiController::class, 'wompiLink'])->name('wompi.link');
    //ventas/acumulado
    Route::post('ventas-acumulado/exportar', [VentasController::class, 'acumuladoExport']);

    Route::get('ventas-por-marcas/exportar', [VentasController::class, 'porMarcasExport']);

    Route::get('ventas-por-utilidades/exportar', [VentasController::class, 'porUtilidadesExport']);

    Route::get('cobros-por-vendedor/exportar', [VentasController::class, 'cobrosPorVendedorExport']);

    Route::post('ventas/importar', [VentasImportController::class, 'importar']);
    Route::get('ventas/plantilla', [VentasImportController::class, 'downloadPlantilla']);

    Route::get('ventas/nums-ids',    [VentasController::class, 'getNumerosIdentificacion']);


?>

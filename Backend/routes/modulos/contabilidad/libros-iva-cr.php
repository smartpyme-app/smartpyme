<?php

use App\Http\Controllers\Api\Contabilidad\LibrosIva\LibrosIvaCrController;
use Illuminate\Support\Facades\Route;

Route::prefix('libro-iva-cr')->group(function () {
    Route::get('/ventas', [LibrosIvaCrController::class, 'reporteDetalleIvaVentas']);
    Route::get('/compras', [LibrosIvaCrController::class, 'reporteDetalleIvaCompras']);
    Route::get('/ventas/descargar-excel', [LibrosIvaCrController::class, 'reporteDetalleIvaVentasExcel']);
    Route::get('/ventas/descargar-csv', [LibrosIvaCrController::class, 'reporteDetalleIvaVentasCsv']);
    Route::get('/compras/descargar-excel', [LibrosIvaCrController::class, 'reporteDetalleIvaComprasExcel']);
    Route::get('/compras/descargar-csv', [LibrosIvaCrController::class, 'reporteDetalleIvaComprasCsv']);
});

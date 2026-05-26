<?php

use App\Http\Controllers\Api\Contabilidad\LibrosIva\LibrosIvaHdController;
use Illuminate\Support\Facades\Route;

Route::prefix('libro-iva-hd')->group(function () {
    Route::get('/ventas', [LibrosIvaHdController::class, 'ventas']);
    Route::get('/ventas/descargar-libro', [LibrosIvaHdController::class, 'ventasLibroExport']);
    Route::get('/compras', [LibrosIvaHdController::class, 'compras']);
    Route::get('/compras/descargar-libro', [LibrosIvaHdController::class, 'comprasLibroExport']);
    Route::get('/retenciones', [LibrosIvaHdController::class, 'retenciones']);
});

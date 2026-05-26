<?php

use App\Http\Controllers\Api\Contabilidad\LibrosIva\LibrosIvaGeneralController;
use Illuminate\Support\Facades\Route;

Route::prefix('libro-iva-general')->group(function () {
    Route::get('/ventas', [LibrosIvaGeneralController::class, 'ventas']);
    Route::get('/ventas/descargar-libro', [LibrosIvaGeneralController::class, 'ventasLibroExport']);
    Route::get('/compras', [LibrosIvaGeneralController::class, 'compras']);
    Route::get('/compras/descargar-libro', [LibrosIvaGeneralController::class, 'comprasLibroExport']);
    Route::get('/retenciones', [LibrosIvaGeneralController::class, 'retenciones']);
});

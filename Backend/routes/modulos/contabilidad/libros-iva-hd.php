<?php

use App\Http\Controllers\Api\Contabilidad\LibrosIva\LibrosIvaHdController;
use Illuminate\Support\Facades\Route;

Route::prefix('libro-iva-hd')->group(function () {
    // Compatibilidad: el libro unificado de ventas pasa a contribuyentes.
    Route::redirect('/ventas', '/api/libro-iva-hd/contribuyentes');
    Route::redirect('/ventas/descargar-libro', '/api/libro-iva-hd/contribuyentes/descargar-libro');

    Route::get('/consumidores', [LibrosIvaHdController::class, 'consumidores']);
    Route::get('/consumidores/descargar-libro', [LibrosIvaHdController::class, 'consumidoresLibroExport']);
    Route::get('/contribuyentes', [LibrosIvaHdController::class, 'contribuyentes']);
    Route::get('/contribuyentes/descargar-libro', [LibrosIvaHdController::class, 'contribuyentesLibroExport']);
    Route::get('/compras', [LibrosIvaHdController::class, 'compras']);
    Route::get('/compras/descargar-libro', [LibrosIvaHdController::class, 'comprasLibroExport']);
    Route::get('/retenciones', [LibrosIvaHdController::class, 'retenciones']);
});

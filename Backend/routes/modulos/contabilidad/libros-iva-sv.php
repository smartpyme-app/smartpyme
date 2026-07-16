<?php

use App\Http\Controllers\Api\Contabilidad\LibrosIva\LibrosIvaSvController;
use Illuminate\Support\Facades\Route;

Route::prefix('libro-iva-sv')->group(function () {
    Route::get('/contribuyentes', [LibrosIvaSvController::class, 'contribuyentes']);
    Route::get('/contribuyentes/descargar-libro', [LibrosIvaSvController::class, 'contribuyentesLibroExport']);
    Route::get('/contribuyentes/descargar-anexo', [LibrosIvaSvController::class, 'contribuyentesAnexoExport']);
    Route::get('/contribuyentes/descargar-dttes', [LibrosIvaSvController::class, 'GlobalDttesExport']);
    Route::get('/contribuyentes/descargar-dttes-pdf', [LibrosIvaSvController::class, 'exportGlobalDttesPdf']);
    Route::get('/contribuyentes/descargar-notas-credito-debito', [LibrosIvaSvController::class, 'notasCreditoDebitoExport']);

    Route::get('/consumidor-final', [LibrosIvaSvController::class, 'consumidores']);
    Route::get('/consumidor-final/descargar-libro', [LibrosIvaSvController::class, 'consumidoresLibroExport']);
    Route::get('/consumidor-final/descargar-anexo', [LibrosIvaSvController::class, 'consumidoresAnexoExport']);

    Route::get('/compras', [LibrosIvaSvController::class, 'compras']);
    Route::get('/compras/descargar-libro', [LibrosIvaSvController::class, 'comprasLibroExport']);
    Route::get('/compras/descargar-anexo', [LibrosIvaSvController::class, 'comprasAnexoExport']);

    Route::get('/anulados', [LibrosIvaSvController::class, 'anulados']);
    Route::get('/anulados/descargar-libro', [LibrosIvaSvController::class, 'anuladosLibroExport']);
    Route::get('/anulados/descargar-anexo', [LibrosIvaSvController::class, 'anuladosAnexoExport']);

    Route::get('/compras-sujetos-excluidos', [LibrosIvaSvController::class, 'comprasSujetosExcluidos']);
    Route::get('/compras-sujetos-excluidos/descargar-libro', [LibrosIvaSvController::class, 'comprasSujetosExcluidosLibroExport']);
    Route::get('/compras-sujetos-excluidos/descargar-anexo', [LibrosIvaSvController::class, 'comprasSujetosExcluidosAnexoExport']);

    Route::get('/retencion1/descargar-libro', [LibrosIvaSvController::class, 'libroRetencion1Export']);
    Route::get('/retencion1/descargar-anexo', [LibrosIvaSvController::class, 'anexoRetencion1Export']);
    Route::get('/percepcion1/descargar-libro', [LibrosIvaSvController::class, 'libroPercepcion1Export']);
    Route::get('/percepcion1/descargar-anexo', [LibrosIvaSvController::class, 'anexoPercepcion1Export']);
    Route::get('/impuesto-turismo', [LibrosIvaSvController::class, 'impuestoTurismoList']);
    Route::get('/impuesto-turismo/descargar-libro', [LibrosIvaSvController::class, 'impuestoTurismoLibroExport']);
});

// Rutas legacy El Salvador (compatibilidad)
Route::get('/libro-iva/contribuyentes', [LibrosIvaSvController::class, 'contribuyentes']);
Route::get('/libro-iva/contribuyentes/descargar-libro', [LibrosIvaSvController::class, 'contribuyentesLibroExport']);
Route::get('/libro-iva/contribuyentes/descargar-anexo', [LibrosIvaSvController::class, 'contribuyentesAnexoExport']);
Route::get('/libro-iva/contribuyentes/descargar-dttes', [LibrosIvaSvController::class, 'GlobalDttesExport']);
Route::get('/libro-iva/contribuyentes/descargar-dttes-pdf', [LibrosIvaSvController::class, 'exportGlobalDttesPdf']);
Route::get('/libro-iva/contribuyentes/descargar-notas-credito-debito', [LibrosIvaSvController::class, 'notasCreditoDebitoExport']);

Route::get('/libro-iva/consumidores/descargar-anexo', [LibrosIvaSvController::class, 'consumidoresAnexoExport']);
Route::get('/libro-iva/consumidores/descargar-dttes', [LibrosIvaSvController::class, 'GlobalDttesExport']);
Route::get('/libro-iva/consumidores/descargar-dttes-pdf', [LibrosIvaSvController::class, 'exportGlobalDttesPdf']);

Route::get('/libro-iva/compras/descargar-anexo', [LibrosIvaSvController::class, 'comprasAnexoExport']);

Route::get('/libro-iva/anulados', [LibrosIvaSvController::class, 'anulados']);
Route::get('/libro-iva/anulados/descargar-libro', [LibrosIvaSvController::class, 'anuladosLibroExport']);
Route::get('/libro-iva/anulados/descargar-anexo', [LibrosIvaSvController::class, 'anuladosAnexoExport']);

Route::get('/libro-iva/compras-sujetos-excluidos', [LibrosIvaSvController::class, 'comprasSujetosExcluidos']);
Route::get('/libro-iva/compras-sujetos-excluidos/descargar-libro', [LibrosIvaSvController::class, 'comprasSujetosExcluidosLibroExport']);
Route::get('/libro-iva/compras-sujetos-excluidos/descargar-anexo', [LibrosIvaSvController::class, 'comprasSujetosExcluidosAnexoExport']);

Route::get('/libro-iva/retencion1/descargar-libro', [LibrosIvaSvController::class, 'libroRetencion1Export']);
Route::get('/libro-iva/retencion1/descargar-anexo', [LibrosIvaSvController::class, 'anexoRetencion1Export']);
Route::get('/libro-iva/percepcion1/descargar-libro', [LibrosIvaSvController::class, 'libroPercepcion1Export']);
Route::get('/libro-iva/percepcion1/descargar-anexo', [LibrosIvaSvController::class, 'anexoPercepcion1Export']);
Route::get('/libro-iva/impuesto-turismo', [LibrosIvaSvController::class, 'impuestoTurismoList']);
Route::get('/libro-iva/impuesto-turismo/descargar-libro', [LibrosIvaSvController::class, 'impuestoTurismoLibroExport']);

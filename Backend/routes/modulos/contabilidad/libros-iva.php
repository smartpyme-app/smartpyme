<?php

use App\Http\Controllers\Api\Contabilidad\LibrosIva\LibrosIvaLegacyController;
use App\Http\Controllers\Api\Contabilidad\LibrosIva\LibrosIvaResumenController;
use Illuminate\Support\Facades\Route;

require base_path('routes/modulos/contabilidad/libros-iva-sv.php');
require base_path('routes/modulos/contabilidad/libros-iva-cr.php');
require base_path('routes/modulos/contabilidad/libros-iva-hd.php');
require base_path('routes/modulos/contabilidad/libros-iva-general.php');

// Resumen fiscal (todos los países)
Route::get('/libro-iva/resumen-fiscal', [LibrosIvaResumenController::class, 'resumenFiscal']);

// Rutas compartidas legacy → despacho por país
Route::get('/libro-iva/consumidores', [LibrosIvaLegacyController::class, 'consumidores']);
Route::get('/libro-iva/consumidores/descargar-libro', [LibrosIvaLegacyController::class, 'consumidoresLibroExport']);
Route::get('/libro-iva/compras', [LibrosIvaLegacyController::class, 'compras']);
Route::get('/libro-iva/compras/descargar-libro', [LibrosIvaLegacyController::class, 'comprasLibroExport']);
Route::get('/libro-iva/retenciones', [LibrosIvaLegacyController::class, 'retenciones']);

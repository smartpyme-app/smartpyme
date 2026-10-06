<?php

use App\Http\Controllers\Api\Contadores\ContadorPortalController;
use Illuminate\Support\Facades\Route;

Route::get('/contadores/empresas', [ContadorPortalController::class, 'empresas']);
Route::get('/contadores/cartera', [ContadorPortalController::class, 'cartera']);
Route::get('/contadores/cumplimiento', [ContadorPortalController::class, 'cumplimiento']);
Route::get('/contadores/libros-iva', [ContadorPortalController::class, 'librosIva']);
Route::get('/contadores/libros-iva/informe', [ContadorPortalController::class, 'librosIvaInforme']);
Route::get('/contadores/libros-iva/export', [ContadorPortalController::class, 'librosIvaExport']);
Route::post('/contadores/cumplimiento/documentos', [ContadorPortalController::class, 'cumplimientoDocumento']);
Route::post('/contadores/cumplimiento/presentado', [ContadorPortalController::class, 'cumplimientoPresentado']);
Route::get('/contadores/cartera/empresa/{idEmpresa}', [ContadorPortalController::class, 'carteraEmpresa']);
Route::post('/contadores/empresas/{idEmpresa}/contexto', [ContadorPortalController::class, 'contexto']);

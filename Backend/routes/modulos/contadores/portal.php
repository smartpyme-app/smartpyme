<?php

use App\Http\Controllers\Api\Contadores\ContadorPortalController;
use Illuminate\Support\Facades\Route;

Route::get('/contadores/empresas', [ContadorPortalController::class, 'empresas']);
Route::get('/contadores/cartera', [ContadorPortalController::class, 'cartera']);
Route::get('/contadores/cartera/empresa/{idEmpresa}', [ContadorPortalController::class, 'carteraEmpresa']);
Route::post('/contadores/empresas/{idEmpresa}/contexto', [ContadorPortalController::class, 'contexto']);

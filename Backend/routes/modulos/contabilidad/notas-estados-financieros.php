<?php

use App\Http\Controllers\Api\Contabilidad\Reportes\NotasEstadosFinancierosController;
use Illuminate\Support\Facades\Route;

Route::middleware('reports.no_cache')->group(function () {
    Route::get('/reportes/notas/estados-financieros/{fecha_inicio}/{fecha_fin}/{type}', [NotasEstadosFinancierosController::class, 'exportar']);
    Route::get('/reportes/estados-financieros/completos/{fecha_inicio}/{fecha_fin}', [NotasEstadosFinancierosController::class, 'exportarCompletos']);
});

Route::prefix('notas-estados-financieros')->group(function () {
    Route::get('/catalogo', [NotasEstadosFinancierosController::class, 'catalogo']);
    Route::post('/generar', [NotasEstadosFinancierosController::class, 'generar']);
    Route::get('/{id}', [NotasEstadosFinancierosController::class, 'show'])->whereNumber('id');
    Route::put('/{id}/manual', [NotasEstadosFinancierosController::class, 'actualizarManual'])->whereNumber('id');
    Route::post('/{id}/emitir', [NotasEstadosFinancierosController::class, 'emitir'])->whereNumber('id');
});

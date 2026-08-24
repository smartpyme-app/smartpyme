<?php

use App\Http\Controllers\Api\Finanzas\AntiguedadSaldosController;

Route::middleware('permission:finanzas.reporteria.ver')->group(function () {
    Route::get('/finanzas/antiguedad-saldos', [AntiguedadSaldosController::class, 'index']);
    Route::get('/finanzas/antiguedad-saldos/pdf', [AntiguedadSaldosController::class, 'pdf']);
    Route::get('/finanzas/antiguedad-saldos/excel', [AntiguedadSaldosController::class, 'excel']);
});

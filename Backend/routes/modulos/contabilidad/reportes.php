<?php

use App\Http\Controllers\Api\Contabilidad\Reportes\GenerarReportesController;

    Route::get('/reportes/diario/auxiliar',             [GenerarReportesController::class, 'generarRepLibroDiarioAux']); //genera el libro diario auxiliar solamente como temporal
    Route::get('/reportes/diario/mayor',        [GenerarReportesController::class, 'generarRepLibroDiarioMayor']); //genera el libro diario mayor solamente como temporal
    Route::get('/reportes/balance/comprobacion', [GenerarReportesController::class, 'generarBalanceComprobacion']);
    Route::get('/reportes/mayorizacion', [GenerarReportesController::class, 'mayorizacion']);
    Route::get('/reportes/balance/general',[GenerarReportesController::class, 'generarBalanceGeneral']);

?>

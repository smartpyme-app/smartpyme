<?php

use App\Http\Controllers\Api\Contabilidad\Reportes\GenerarReportesController;

    Route::get('/reportes/libro/diario/{startDate}/{endDate}/{type}',        [GenerarReportesController::class, 'generarRepLibroDiario']); //genera el libro diario
    Route::get('/reportes/libro/diario/mayor/{startDate}/{endDate}/{type}/', [GenerarReportesController::class, 'generarRepLibroDiarioMayor']); //genera el libro diario mayor solamente como temporal
//    Route::get('/reportes/diario/mayor/{startDate}/{endDate}',             [GenerarReportesController::class, 'generarRepLibroDiarioMayor']); //genera el libro diario mayor solamente como temporal
    Route::get('/reportes/movimiento/cuenta/{startDate}/{endDate}/{cuenta}', [GenerarReportesController::class, 'generarRepMovCuenta']); //genera el libro diario mayor solamente como temporal
    Route::get('/reportes/balance/comprobacion/{startDate}/{endDate}/{type}',[GenerarReportesController::class, 'generarRepBalanceComprobacion']);
    Route::get('/reportes/mayorizacion',                                     [GenerarReportesController::class, 'mayorizacion']);
    Route::get('/reportes/balance/general',                                  [GenerarReportesController::class, 'generarBalanceGeneral']);

?>

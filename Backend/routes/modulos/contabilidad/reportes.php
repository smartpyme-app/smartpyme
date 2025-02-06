<?php

use App\Http\Controllers\Api\Contabilidad\Reportes\GenerarReportesController;
use Illuminate\Support\Facades\Route;

    Route::get('/reportes/libro/diario/{month}/{year}/{type}',        [GenerarReportesController::class, 'generarRepLibroDiario']); //genera el libro diario
    Route::get('/reportes/libro/diario/mayor/{month}/{year}/{type}/', [GenerarReportesController::class, 'generarRepLibroDiarioMayor']); //genera el libro diario mayor solamente como temporal
//    Route::get('/reportes/diario/mayor/{month}/{year}',             [GenerarReportesController::class, 'generarRepLibroDiarioMayor']); //genera el libro diario mayor solamente como temporal
    Route::get('/reportes/movimiento/cuenta/{month}/{year}/{cuenta}', [GenerarReportesController::class, 'generarRepMovCuenta']); //genera el libro diario mayor solamente como temporal
    Route::get('/reportes/balance/comprobacion/{month}/{year}/{type}',[GenerarReportesController::class, 'generarRepBalanceComprobacion']);
    Route::get('/reportes/mayorizacion',                                     [GenerarReportesController::class, 'mayorizacion']);
    Route::get('/reportes/balance/general',                                  [GenerarReportesController::class, 'generarBalanceGeneral']);

?>

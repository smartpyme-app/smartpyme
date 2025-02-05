<?php

use App\Http\Controllers\Api\Contabilidad\Reportes\GenerarReportesController;
//use Route;
use Illuminate\Support\Facades\Route;

    Route::get('/reportes/libro/diario/{month}/{year}/{cuenta}/{type}',        [GenerarReportesController::class, 'generarRepLibroDiario']); //genera el libro diario
    Route::get('/reportes/libro/diario/mayor/{month}/{year}/{cuenta}/{type}/', [GenerarReportesController::class, 'generarRepLibroDiarioMayor']); //genera el libro diario mayor solamente como temporal
//    Route::get('/reportes/diario/mayor/{month}/{year}',             [GenerarReportesController::class, 'generarRepLibroDiarioMayor']); //genera el libro diario mayor solamente como temporal
    Route::get('/reportes/movimiento/cuenta/{month}/{year}/{cuenta}', [GenerarReportesController::class, 'generarRepMovCuenta']); //genera el libro diario mayor solamente como temporal
    Route::get('/reportes/balance/comprobacion/{month}/{year}/{cuenta}/{type}',[GenerarReportesController::class, 'generarRepBalanceComprobacion']);
    Route::get('/reportes/mayorizacion',                                     [GenerarReportesController::class, 'mayorizacion']);
    Route::get('/reportes/balance/general',                                  [GenerarReportesController::class, 'generarBalanceGeneral']);

?>

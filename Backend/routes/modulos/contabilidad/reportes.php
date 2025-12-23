<?php

use App\Http\Controllers\Api\Contabilidad\Reportes\GenerarReportesController;
use Illuminate\Support\Facades\Route;

    Route::get('/reportes/libro/diario/{fecha_inicio}/{fecha_fin}/{cuenta}/{type}',        [GenerarReportesController::class, 'generarRepLibroDiario']); //genera el libro diario
    Route::get('/reportes/libro/diario/mayor/{fecha_inicio}/{fecha_fin}/{cuenta}/{type}/', [GenerarReportesController::class, 'generarRepLibroDiarioMayor']); //genera el libro diario mayor solamente como temporal
//    Route::get('/reportes/diario/mayor/{month}/{year}',             [GenerarReportesController::class, 'generarRepLibroDiarioMayor']); //genera el libro diario mayor solamente como temporal
    Route::get('/reportes/movimiento/cuenta/{fecha_inicio}/{fecha_fin}/{cuenta}', [GenerarReportesController::class, 'generarRepMovCuenta']); //genera el libro diario mayor solamente como temporal
    Route::get('/reportes/balance/comprobacion/{fecha_inicio}/{fecha_fin}/{cuenta}/{type}',[GenerarReportesController::class, 'generarRepBalanceComprobacion']);
    Route::get('/reportes/mayorizacion',                                     [GenerarReportesController::class, 'mayorizacion']);
    Route::get('/reportes/balance/general/{fecha_inicio}/{fecha_fin}/{type}',            [GenerarReportesController::class, 'generarBalanceGeneral']);
    Route::get('/reportes/estado/resultados/{fecha_inicio}/{fecha_fin}/{type}',          [GenerarReportesController::class, 'generarEstadoResultados']);

?>

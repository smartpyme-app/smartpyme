<?php 

use App\Http\Controllers\Api\Admin\MHController;
use App\Http\Controllers\Api\Admin\MHDTEController;

    Route::get('/paises', [MHController::class, 'paises']);
    Route::get('/municipios', [MHController::class, 'municipios']);
    Route::get('/distritos', [MHController::class, 'distritos']);
    Route::get('/departamentos', [MHController::class, 'departamentos']);
    Route::get('/actividades_economicas', [MHController::class, 'actividadesEconomicas']);
    Route::get('/unidades', [MHController::class, 'unidades']);

    // Generar DTE
    Route::post('/generarDTE',          [MHDTEController::class, 'generarDTE']);
    Route::post('/generarDTENotaCredito',          [MHDTEController::class, 'generarDTENotaCredito']);
    Route::post('/generarDTESujetoExcluidoGasto', [MHDTEController::class, 'generarDTESujetoExcluidoGasto']);
    Route::post('/generarDTESujetoExcluidoCompra', [MHDTEController::class, 'generarDTESujetoExcluidoCompra']);
    Route::post('/generarDTEAnuladoSujetoExcluidoGasto',    [MHDTEController::class, 'generarDTEAnuladoSujetoExcluidoGasto']);
    Route::post('/generarDTEAnuladoSujetoExcluidoCompra',    [MHDTEController::class, 'generarDTEAnuladoSujetoExcluidoCompra']);
    Route::post('/generarContingencia',    [MHDTEController::class, 'generarContingencia']);
    Route::post('/generarDTEAnulado',    [MHDTEController::class, 'generarDTEAnulado']);

    // Generar facturas
    Route::get('/reporte/ticket/{id}',  [MHDTEController::class, 'generarTicket']);

    // Enviar DTE
    Route::post('/enviarDTE',           [MHDTEController::class, 'enviarDTE']);
    // Anular DTE
    Route::post('/anularDTE',           [MHDTEController::class, 'anularDTE']);
    // Generar DTE JSON
    Route::get('/reporte/dte/{id}/{tipo}',     [MHDTEController::class, 'generarDTEPDF']);
    // Generar DTE PDF
    Route::get('/reporte/dte-json/{id}/{tipo}',    [MHDTEController::class, 'generarDTEJSON']);
    
?>

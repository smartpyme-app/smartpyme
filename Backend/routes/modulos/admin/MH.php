<?php 

use App\Http\Controllers\Api\Admin\MHController;

    Route::get('/municipios', [MHController::class, 'municipios']);
    Route::get('/departamentos', [MHController::class, 'departamentos']);
    Route::get('/actividades_economicas', [MHController::class, 'actividadesEconomicas']);
    Route::get('/unidades', [MHController::class, 'unidades']);

    // Generar facturas
    Route::get('/reporte/ticket/{id}',  [MHController::class, 'generarTicket']);
    // Emitir DTE
    Route::post('/emitirDTE',           [MHController::class, 'emitirDTE']);
    // Generar DTE
    Route::post('/generarDTE',          [MHController::class, 'generarDTE']);
    // Enviar DTE
    Route::post('/enviarDTE',           [MHController::class, 'enviarDTE']);
    // Anular DTE
    Route::post('/anularDTE',           [MHController::class, 'anularDTE']);
    // Generar DTE JSON
    Route::get('/reporte/dte/{id}',     [MHController::class, 'generarDTEPDF']);
    // Generar DTE PDF
    Route::get('/reporte/dte-json/{id}',    [MHController::class, 'generarDTEJSON']);

?>

<?php 

use App\Http\Controllers\Api\Admin\MHController;
use App\Http\Controllers\Api\Admin\MHDTEController;

    Route::get('/municipios', [MHController::class, 'municipios']);
    Route::get('/departamentos', [MHController::class, 'departamentos']);
    Route::get('/actividades_economicas', [MHController::class, 'actividadesEconomicas']);
    Route::get('/unidades', [MHController::class, 'unidades']);

    // Generar DTE
    Route::post('/generarDTE',          [MHDTEController::class, 'generarDTE']);
    Route::post('/generarDTESujetoExcluido', [MHDTEController::class, 'generarDTESujetoExcluido']);
    Route::post('/generarDTEAnuladoSujetoExcluido',    [MHDTEController::class, 'generarDTEAnuladoSujetoExcluido']);
    Route::post('/generarContingencia',    [MHDTEController::class, 'generarContingencia']);
    Route::post('/generarDTEAnulado',    [MHDTEController::class, 'generarDTEAnulado']);

    // Generar facturas
    Route::get('/reporte/ticket/{id}',  [MHDTEController::class, 'generarTicket']);

    // Enviar DTE
    Route::post('/enviarDTE',           [MHDTEController::class, 'enviarDTE']);
    // Anular DTE
    Route::post('/anularDTE',           [MHDTEController::class, 'anularDTE']);
    // Generar DTE JSON
    Route::get('/reporte/dte/{id}',     [MHDTEController::class, 'generarDTEPDF']);
    Route::get('/reporte/dte/sujetoexcluido/{id}',     [MHDTEController::class, 'generarDTEPDFSujetoExcluido']);
    // Generar DTE PDF
    Route::get('/reporte/dte-json/{id}',    [MHDTEController::class, 'generarDTEJSON']);
    
    Route::get('/reporte/dte-json/sujetoexcluido/{id}',    [MHDTEController::class, 'generarDTEJSONSujetoExcluido']);

?>

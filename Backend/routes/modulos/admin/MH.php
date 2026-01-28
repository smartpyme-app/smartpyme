<?php 

use App\Http\Controllers\Api\Admin\MHController;
use App\Http\Controllers\Api\Admin\FacturacionElectronicaController;

    // Rutas de catálogos (MHController - específico de El Salvador)
    Route::get('/paises', [MHController::class, 'paises']);
    Route::get('/municipios', [MHController::class, 'municipios']);
    Route::get('/distritos', [MHController::class, 'distritos']);
    Route::get('/departamentos', [MHController::class, 'departamentos']);
    Route::get('/actividades_economicas', [MHController::class, 'actividadesEconomicas']);
    Route::get('/unidades', [MHController::class, 'unidades']);
    Route::get('/recintos', [MHController::class, 'recintos']);
    Route::get('/regimenes', [MHController::class, 'regimenes']);
    Route::get('/incoterms', [MHController::class, 'incoterms']);

    // ============================================
    // RUTAS - Facturación Electrónica Multi-País
    // ============================================
    Route::prefix('fe')->group(function () {
        // Generar DTE
        Route::post('/generarDTE', [FacturacionElectronicaController::class, 'generarDTE']);
        Route::post('/generarDTENotaCredito', [FacturacionElectronicaController::class, 'generarDTENotaCredito']);
        Route::post('/generarDTESujetoExcluidoGasto', [FacturacionElectronicaController::class, 'generarDTESujetoExcluidoGasto']);
        Route::post('/generarDTESujetoExcluidoCompra', [FacturacionElectronicaController::class, 'generarDTESujetoExcluidoCompra']);
        
        // Generar DTE Anulado (solo genera el documento, no lo anula)
        Route::post('/generarDTEAnulado', [FacturacionElectronicaController::class, 'generarDTEAnulado']);
        Route::post('/generarDTEAnuladoSujetoExcluidoGasto', [FacturacionElectronicaController::class, 'generarDTEAnuladoSujetoExcluidoGasto']);
        Route::post('/generarDTEAnuladoSujetoExcluidoCompra', [FacturacionElectronicaController::class, 'generarDTEAnuladoSujetoExcluidoCompra']);
        
        // Anular DTE
        Route::post('/anularDTE', [FacturacionElectronicaController::class, 'anularDTE']);
        
        // Consultar DTE
        Route::post('/consultarDTE', [FacturacionElectronicaController::class, 'consultarDTE']);
        
        // Enviar DTE
        Route::post('/enviarDTE', [FacturacionElectronicaController::class, 'enviarDTE']);
        
        // Contingencia
        Route::post('/generarContingencia', [FacturacionElectronicaController::class, 'generarContingencia']);
        
        // Generar reportes
        Route::get('/reporte/dte/{id}/{tipo}', [FacturacionElectronicaController::class, 'generarDTEPDF']);
        Route::get('/reporte/dte-json/{id}/{tipo}', [FacturacionElectronicaController::class, 'generarDTEJSON']);
        Route::get('/reporte/ticket/{id}', [FacturacionElectronicaController::class, 'generarTicket']);
    });

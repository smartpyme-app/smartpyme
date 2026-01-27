<?php 

use App\Http\Controllers\Api\Admin\MHController;
use App\Http\Controllers\Api\Admin\MHDTEController;
use App\Http\Controllers\Api\Admin\FacturacionElectronicaController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
    // NUEVAS RUTAS - Facturación Electrónica Multi-País
    // ============================================
    Route::prefix('fe')->group(function () {
        // Generar DTE
        Route::post('/generarDTE', [FacturacionElectronicaController::class, 'generarDTE']);
        Route::post('/generarDTENotaCredito', [FacturacionElectronicaController::class, 'generarDTENotaCredito']);
        Route::post('/generarDTESujetoExcluidoGasto', [FacturacionElectronicaController::class, 'generarDTESujetoExcluidoGasto']);
        Route::post('/generarDTESujetoExcluidoCompra', [FacturacionElectronicaController::class, 'generarDTESujetoExcluidoCompra']);
        
        // Anular DTE
        Route::post('/anularDTE', [FacturacionElectronicaController::class, 'anularDTE']);
        
        // Consultar DTE
        Route::post('/consultarDTE', [FacturacionElectronicaController::class, 'consultarDTE']);
        
        // Enviar DTE
        Route::post('/enviarDTE', [FacturacionElectronicaController::class, 'enviarDTE']);
        
        // Generar reportes
        Route::get('/reporte/dte/{id}/{tipo}', [FacturacionElectronicaController::class, 'generarDTEPDF']);
        Route::get('/reporte/dte-json/{id}/{tipo}', [FacturacionElectronicaController::class, 'generarDTEJSON']);
    });

    // ============================================
    // RUTAS ANTIGUAS - DEPRECATED (Mantienen compatibilidad)
    // ============================================
    // Estas rutas redirigen al nuevo controlador pero logean deprecación
    Route::post('/generarDTE', function (Request $request) {
        Log::warning('Ruta deprecated usada: /generarDTE. Usar /fe/generarDTE');
        return app(FacturacionElectronicaController::class)->generarDTE($request);
    })->middleware('deprecated');
    
    Route::post('/generarDTENotaCredito', function (Request $request) {
        Log::warning('Ruta deprecated usada: /generarDTENotaCredito. Usar /fe/generarDTENotaCredito');
        return app(FacturacionElectronicaController::class)->generarDTENotaCredito($request);
    })->middleware('deprecated');
    
    Route::post('/generarDTESujetoExcluidoGasto', function (Request $request) {
        Log::warning('Ruta deprecated usada: /generarDTESujetoExcluidoGasto. Usar /fe/generarDTESujetoExcluidoGasto');
        return app(FacturacionElectronicaController::class)->generarDTESujetoExcluidoGasto($request);
    })->middleware('deprecated');
    
    Route::post('/generarDTESujetoExcluidoCompra', function (Request $request) {
        Log::warning('Ruta deprecated usada: /generarDTESujetoExcluidoCompra. Usar /fe/generarDTESujetoExcluidoCompra');
        return app(FacturacionElectronicaController::class)->generarDTESujetoExcluidoCompra($request);
    })->middleware('deprecated');
    
    Route::post('/anularDTE', function (Request $request) {
        Log::warning('Ruta deprecated usada: /anularDTE. Usar /fe/anularDTE');
        return app(FacturacionElectronicaController::class)->anularDTE($request);
    })->middleware('deprecated');
    
    Route::post('/consultarDTE', function (Request $request) {
        Log::warning('Ruta deprecated usada: /consultarDTE. Usar /fe/consultarDTE');
        return app(FacturacionElectronicaController::class)->consultarDTE($request);
    })->middleware('deprecated');
    
    Route::post('/enviarDTE', function (Request $request) {
        Log::warning('Ruta deprecated usada: /enviarDTE. Usar /fe/enviarDTE');
        return app(FacturacionElectronicaController::class)->enviarDTE($request);
    })->middleware('deprecated');
    
    Route::get('/reporte/dte/{id}/{tipo}', function ($id, $tipo, Request $request) {
        Log::warning('Ruta deprecated usada: /reporte/dte/{id}/{tipo}. Usar /fe/reporte/dte/{id}/{tipo}');
        return app(FacturacionElectronicaController::class)->generarDTEPDF($id, $tipo, $request);
    })->middleware('deprecated');
    
    Route::get('/reporte/dte-json/{id}/{tipo}', function ($id, $tipo, Request $request) {
        Log::warning('Ruta deprecated usada: /reporte/dte-json/{id}/{tipo}. Usar /fe/reporte/dte-json/{id}/{tipo}');
        return app(FacturacionElectronicaController::class)->generarDTEJSON($id, $tipo, $request);
    })->middleware('deprecated');

    // Rutas que aún no tienen equivalente en el nuevo controlador (mantener temporalmente)
    Route::post('/generarDTEAnuladoSujetoExcluidoGasto', [MHDTEController::class, 'generarDTEAnuladoSujetoExcluidoGasto']);
    Route::post('/generarDTEAnuladoSujetoExcluidoCompra', [MHDTEController::class, 'generarDTEAnuladoSujetoExcluidoCompra']);
    Route::post('/generarContingencia', [MHDTEController::class, 'generarContingencia']);
    Route::post('/generarDTEAnulado', [MHDTEController::class, 'generarDTEAnulado']);
    Route::get('/reporte/ticket/{id}', [MHDTEController::class, 'generarTicket']);
    
?>

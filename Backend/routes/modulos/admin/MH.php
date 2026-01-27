<?php 

use App\Http\Controllers\Api\Admin\MHController;
use App\Http\Controllers\Api\Admin\MHDTEController;
use App\Http\Controllers\Api\Admin\FacturacionElectronicaController;
use App\Http\Requests\MH\GenerarDTERequest;
use App\Http\Requests\MH\GenerarDTENotaCreditoRequest;
use App\Http\Requests\MH\GenerarDTESujetoExcluidoGastoRequest;
use App\Http\Requests\MH\GenerarDTESujetoExcluidoCompraRequest;
use App\Http\Requests\MH\AnularDTERequest;
use App\Http\Requests\MH\ConsultarDTERequest;
use App\Http\Requests\MH\EnviarDTERequest;
use App\Http\Requests\MH\GenerarDTEPDFRequest;
use App\Http\Requests\MH\GenerarDTEJSONRequest;
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
        $formRequest = GenerarDTERequest::createFrom($request);
        $formRequest->setContainer(app());
        $formRequest->validateResolved();
        return app(FacturacionElectronicaController::class)->generarDTE($formRequest);
    });
    
    Route::post('/generarDTENotaCredito', function (Request $request) {
        Log::warning('Ruta deprecated usada: /generarDTENotaCredito. Usar /fe/generarDTENotaCredito');
        $formRequest = GenerarDTENotaCreditoRequest::createFrom($request);
        $formRequest->setContainer(app());
        $formRequest->validateResolved();
        return app(FacturacionElectronicaController::class)->generarDTENotaCredito($formRequest);
    });
    
    Route::post('/generarDTESujetoExcluidoGasto', function (Request $request) {
        Log::warning('Ruta deprecated usada: /generarDTESujetoExcluidoGasto. Usar /fe/generarDTESujetoExcluidoGasto');
        $formRequest = GenerarDTESujetoExcluidoGastoRequest::createFrom($request);
        $formRequest->setContainer(app());
        $formRequest->validateResolved();
        return app(FacturacionElectronicaController::class)->generarDTESujetoExcluidoGasto($formRequest);
    });
    
    Route::post('/generarDTESujetoExcluidoCompra', function (Request $request) {
        Log::warning('Ruta deprecated usada: /generarDTESujetoExcluidoCompra. Usar /fe/generarDTESujetoExcluidoCompra');
        $formRequest = GenerarDTESujetoExcluidoCompraRequest::createFrom($request);
        $formRequest->setContainer(app());
        $formRequest->validateResolved();
        return app(FacturacionElectronicaController::class)->generarDTESujetoExcluidoCompra($formRequest);
    });
    
    Route::post('/anularDTE', function (Request $request) {
        Log::warning('Ruta deprecated usada: /anularDTE. Usar /fe/anularDTE');
        $formRequest = AnularDTERequest::createFrom($request);
        $formRequest->setContainer(app());
        $formRequest->validateResolved();
        return app(FacturacionElectronicaController::class)->anularDTE($formRequest);
    });
    
    Route::post('/consultarDTE', function (Request $request) {
        Log::warning('Ruta deprecated usada: /consultarDTE. Usar /fe/consultarDTE');
        $formRequest = ConsultarDTERequest::createFrom($request);
        $formRequest->setContainer(app());
        $formRequest->validateResolved();
        return app(FacturacionElectronicaController::class)->consultarDTE($formRequest);
    });
    
    Route::post('/enviarDTE', function (Request $request) {
        Log::warning('Ruta deprecated usada: /enviarDTE. Usar /fe/enviarDTE');
        $formRequest = EnviarDTERequest::createFrom($request);
        $formRequest->setContainer(app());
        $formRequest->validateResolved();
        return app(FacturacionElectronicaController::class)->enviarDTE($formRequest);
    });
    
    Route::get('/reporte/dte/{id}/{tipo}', function ($id, $tipo, Request $request) {
        Log::warning('Ruta deprecated usada: /reporte/dte/{id}/{tipo}. Usar /fe/reporte/dte/{id}/{tipo}');
        $formRequest = GenerarDTEPDFRequest::createFrom($request);
        $formRequest->setContainer(app());
        $formRequest->validateResolved();
        return app(FacturacionElectronicaController::class)->generarDTEPDF($id, $tipo, $formRequest);
    });
    
    Route::get('/reporte/dte-json/{id}/{tipo}', function ($id, $tipo, Request $request) {
        Log::warning('Ruta deprecated usada: /reporte/dte-json/{id}/{tipo}. Usar /fe/reporte/dte-json/{id}/{tipo}');
        $formRequest = GenerarDTEJSONRequest::createFrom($request);
        $formRequest->setContainer(app());
        $formRequest->validateResolved();
        return app(FacturacionElectronicaController::class)->generarDTEJSON($id, $tipo, $formRequest);
    });

    // Rutas que aún no tienen equivalente en el nuevo controlador (mantener temporalmente)
    Route::post('/generarDTEAnuladoSujetoExcluidoGasto', [MHDTEController::class, 'generarDTEAnuladoSujetoExcluidoGasto']);
    Route::post('/generarDTEAnuladoSujetoExcluidoCompra', [MHDTEController::class, 'generarDTEAnuladoSujetoExcluidoCompra']);
    Route::post('/generarContingencia', [MHDTEController::class, 'generarContingencia']);
    Route::post('/generarDTEAnulado', [MHDTEController::class, 'generarDTEAnulado']);
    Route::get('/reporte/ticket/{id}', [MHDTEController::class, 'generarTicket']);
    
?>

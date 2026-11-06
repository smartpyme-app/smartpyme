<?php

use App\Http\Controllers\Api\Admin\MHController;
use App\Http\Controllers\Api\Admin\MHDTEController;

    $pEmitirFe = config('permissions.PERMISSION_VENTAS.facturacion_electronica.emitir');
    $pVentasRegVer = config('permissions.PERMISSION_VENTAS.registros.ver');

    Route::get('/paises', [MHController::class, 'paises']);
    Route::get('/municipios', [MHController::class, 'municipios']);
    Route::get('/distritos', [MHController::class, 'distritos']);
    Route::get('/departamentos', [MHController::class, 'departamentos']);
    Route::get('/actividades_economicas', [MHController::class, 'actividadesEconomicas']);
    Route::get('/unidades', [MHController::class, 'unidades']);
    Route::get('/recintos', [MHController::class, 'recintos']);
    Route::get('/regimenes', [MHController::class, 'regimenes']);
    Route::get('/incoterms', [MHController::class, 'incoterms']);

    // Generar DTE (SP-2340: requiere permiso emitir FE)
    Route::post('/generarDTE',          [MHDTEController::class, 'generarDTE'])->middleware('permission:'.$pEmitirFe);
    Route::post('/generarDTENotaCredito',          [MHDTEController::class, 'generarDTENotaCredito'])->middleware('permission:'.$pEmitirFe);
    Route::post('/generarDTESujetoExcluidoGasto', [MHDTEController::class, 'generarDTESujetoExcluidoGasto'])->middleware('permission:'.$pEmitirFe);
    Route::post('/generarDTESujetoExcluidoCompra', [MHDTEController::class, 'generarDTESujetoExcluidoCompra'])->middleware('permission:'.$pEmitirFe);
    Route::post('/generarDTEAnuladoSujetoExcluidoGasto',    [MHDTEController::class, 'generarDTEAnuladoSujetoExcluidoGasto'])->middleware('permission:'.$pEmitirFe);
    Route::post('/generarDTEAnuladoSujetoExcluidoCompra',    [MHDTEController::class, 'generarDTEAnuladoSujetoExcluidoCompra'])->middleware('permission:'.$pEmitirFe);
    Route::post('/generarContingencia',    [MHDTEController::class, 'generarContingencia'])->middleware('permission:'.$pEmitirFe);
    Route::post('/generarDTEAnulado',    [MHDTEController::class, 'generarDTEAnulado'])->middleware('permission:'.$pEmitirFe);

    Route::post('/consultarDTE',    [MHDTEController::class, 'consultarDTE'])->middleware('permission:'.$pVentasRegVer);

    // Generar facturas
    Route::get('/reporte/ticket/{id}',  [MHDTEController::class, 'generarTicket'])->middleware('permission:'.$pVentasRegVer);

    // Enviar DTE
    Route::post('/enviarDTE',           [MHDTEController::class, 'enviarDTE'])->middleware('permission:'.$pEmitirFe);
    // Anular DTE
    Route::post('/anularDTE',           [MHDTEController::class, 'anularDTE'])->middleware('permission:'.$pEmitirFe);
    Route::get('/reporte/dte/{id}/{tipo}',     [MHDTEController::class, 'generarDTEPDF'])->middleware('permission:'.$pVentasRegVer);
    Route::get('/reporte/dte-json/{id}/{tipo}',    [MHDTEController::class, 'generarDTEJSON'])->middleware('permission:'.$pVentasRegVer);
    Route::get('/reporte/dte-xml/{id}/{tipo}',    [MHDTEController::class, 'generarDteXmlCostaRica'])->middleware('permission:'.$pVentasRegVer);
    
?>

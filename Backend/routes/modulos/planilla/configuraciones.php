<?php

use App\Http\Controllers\Api\Planilla\ConfiguracionPlanillaController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'planillas', 'middleware' => ['jwt.auth']], function () {
    Route::controller(ConfiguracionPlanillaController::class)->group(function () {
        Route::get('/configuracion-planilla', 'show')->middleware('permission:planilla.configuracion.ver');
        Route::post('/configuracion-planilla', 'update')->middleware('permission:planilla.configuracion.editar');
        Route::post('/configuracion-planilla/importar-base', 'importarBase')->middleware('permission:planilla.configuracion.crear');
        // Alias FE (misma acción que importar-base)
        Route::post('/configuracion-planilla/importar-plantilla', 'importarPlantilla')->middleware('permission:planilla.configuracion.crear');
        Route::get('/configuracion-planilla/plantillas', 'obtenerPlantillas')->middleware('permission:planilla.configuracion.ver');
        Route::get('/configuracion-planilla/tipos-conceptos', 'obtenerTiposConceptos')->middleware('permission:planilla.configuracion.ver');
        Route::post('/configuracion-planilla/probar', 'probarCalculo')->middleware('permission:planilla.configuracion.ver');
        Route::get('/configuracion-planilla/historial', 'historial')->middleware('permission:planilla.configuracion.ver');
        Route::get('/configuracion-planilla/verificar-personalizada', 'verificarPersonalizada')->middleware('permission:planilla.configuracion.ver');
        Route::get('/configuracion-planilla/pais-info', 'obtenerInformacionPais')->middleware('permission:planilla.configuracion.ver');
        Route::get('/configuracion-planilla/conceptos-tabla', 'obtenerConceptosParaTabla')->middleware('permission:planilla.configuracion.ver');
        Route::post('/configuracion-planilla/calcular-descuentos', 'calcularDescuentos')->middleware('permission:planilla.configuracion.ver');
    });
});

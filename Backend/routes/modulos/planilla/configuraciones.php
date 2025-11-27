<?php

use App\Http\Controllers\Api\Planilla\ConfiguracionPlanillaController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'planillas', 'middleware' => ['jwt.auth']], function () {
    Route::controller(ConfiguracionPlanillaController::class)->group(function () {
            Route::get('/configuracion-planilla', [ConfiguracionPlanillaController::class, 'show']);
            Route::post('/configuracion-planilla', [ConfiguracionPlanillaController::class, 'update']);
            Route::get('/configuracion-planilla/plantillas', [ConfiguracionPlanillaController::class, 'obtenerPlantillas']);
            Route::get('/configuracion-planilla/tipos-conceptos', [ConfiguracionPlanillaController::class, 'obtenerTiposConceptos']);
            Route::post('/configuracion-planilla/probar', [ConfiguracionPlanillaController::class, 'probarCalculo']);
            Route::get('/configuracion-planilla/historial', [ConfiguracionPlanillaController::class, 'historial']);

            Route::get('/configuracion-planilla/verificar-personalizada', [ConfiguracionPlanillaController::class, 'verificarPersonalizada']);
            Route::get('/configuracion-planilla/pais-info', [ConfiguracionPlanillaController::class, 'obtenerInformacionPais']);
            Route::get('/configuracion-planilla/conceptos-tabla', [ConfiguracionPlanillaController::class, 'obtenerConceptosParaTabla']);

            Route::post('/configuracion-planilla/calcular-descuentos', [ConfiguracionPlanillaController::class, 'calcularDescuentos']);

    });
});

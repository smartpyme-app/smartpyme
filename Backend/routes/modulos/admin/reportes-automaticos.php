<?php 
use App\Http\Controllers\Api\Admin\ReporteConfiguracionController;
use Illuminate\Support\Facades\Route;


    Route::get('reportes-configuracion', [ReporteConfiguracionController::class, 'index']);
    
    Route::post('reportes-configuracion', [ReporteConfiguracionController::class, 'store']);
    
    Route::get('reportes-configuracion/{id}', [ReporteConfiguracionController::class, 'show']);
    
    Route::put('reportes-configuracion/estado/{id}', [ReporteConfiguracionController::class, 'updateEstado']);
        
    Route::delete('reportes-configuracion/{id}', [ReporteConfiguracionController::class, 'destroy']);
    
    Route::post('reportes-configuracion/enviar-prueba', [ReporteConfiguracionController::class, 'enviarPrueba']);

    ?>
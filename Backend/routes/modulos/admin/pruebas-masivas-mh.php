<?php

use App\Http\Controllers\Api\Admin\MHPruebasMasivasController;

Route::group(['middleware' => ['jwt.auth'], 'prefix' => 'mh/pruebas-masivas'], function () {
    Route::get('estadisticas', [MHPruebasMasivasController::class, 'estadisticas']);
    Route::get('documentos-base', [MHPruebasMasivasController::class, 'documentosBase']);
    Route::post('ejecutar', [MHPruebasMasivasController::class, 'ejecutar']);
    Route::delete('limpiar', [MHPruebasMasivasController::class, 'limpiarDocumentosPrueba']);
    Route::delete('estadisticas/reiniciar', [MHPruebasMasivasController::class, 'reiniciarEstadisticas']);
    
});


?>

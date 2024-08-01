<?php

use App\Http\Controllers\Api\Contabilidad\ConfiguracionController;

    Route::post('/contabilidad/configuracion',             [ConfiguracionController::class, 'store']);
    Route::get('/contabilidad/configuracion/{id}',         [ConfiguracionController::class, 'read']);


?>

<?php

use App\Http\Controllers\Api\SuperAdmin\ActivosPlantillasController;

Route::middleware('role:super_admin')->group(function () {
    Route::get('/activos-fijos/plantillas/{cod_pais}', [ActivosPlantillasController::class, 'index']);
    Route::post('/activos-fijos/plantilla', [ActivosPlantillasController::class, 'store']);
    Route::delete('/activos-fijos/plantilla/{id}', [ActivosPlantillasController::class, 'delete']);
});

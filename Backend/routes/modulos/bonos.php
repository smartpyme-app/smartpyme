<?php

use App\Http\Controllers\Api\Bonos\BonoEvaluacionController;
use App\Http\Controllers\Api\Bonos\BonoGeneradoController;
use App\Http\Controllers\Api\Bonos\BonoReglaController;
use Illuminate\Support\Facades\Route;

Route::middleware(['verificar.funcionalidad:bonos-vendedores'])->group(function () {
    Route::get('bonos/reglas', [BonoReglaController::class, 'index']);
    Route::post('bonos/reglas', [BonoReglaController::class, 'store']);
    Route::get('bonos/reglas/{id}', [BonoReglaController::class, 'show']);
    Route::put('bonos/reglas/{id}', [BonoReglaController::class, 'update']);
    Route::delete('bonos/reglas/{id}', [BonoReglaController::class, 'destroy']);

    Route::post('bonos/evaluar', [BonoEvaluacionController::class, 'evaluar']);

    Route::get('bonos/generados', [BonoGeneradoController::class, 'index']);
    Route::post('bonos/generados/manual', [BonoGeneradoController::class, 'storeManual']);
    Route::get('bonos/generados/{id}/comprobante', [BonoGeneradoController::class, 'comprobantePdf']);
    Route::post('bonos/generados/{id}/aprobar', [BonoGeneradoController::class, 'aprobar']);
    Route::post('bonos/generados/{id}/pagar', [BonoGeneradoController::class, 'pagar']);
});

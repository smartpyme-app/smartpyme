<?php

use App\Http\Controllers\Api\Clinica\ProductosClinicosController;
use Illuminate\Support\Facades\Route;

Route::middleware(['verificar.funcionalidad:clinica-pacientes'])->group(function () {
    Route::get('clinica/medicamentos-productos', [ProductosClinicosController::class, 'index'])
        ->middleware('permission:clinica.medicamentos.ver');
    Route::get('clinica/medicamentos-productos/{idProducto}', [ProductosClinicosController::class, 'show'])
        ->middleware('permission:clinica.medicamentos.ver');
    Route::put('clinica/medicamentos-productos/{idProducto}', [ProductosClinicosController::class, 'upsert'])
        ->middleware('permission:clinica.medicamentos.configurar');
});

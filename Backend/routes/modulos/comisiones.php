<?php

use App\Http\Controllers\Api\Comisiones\ComisionConfigController;
use App\Http\Controllers\Api\Comisiones\ComisionLiquidacionController;
use App\Http\Controllers\Api\Comisiones\ComisionPeriodoController;
use App\Http\Controllers\Api\Comisiones\ComisionReporteController;
use Illuminate\Support\Facades\Route;

Route::middleware(['verificar.funcionalidad:comisiones-vendedores'])->group(function () {
    Route::get('comisiones/config/categorias', [ComisionConfigController::class, 'listarCategorias']);
    Route::put('comisiones/config/categorias/{id_categoria}', [ComisionConfigController::class, 'actualizarCategoria']);
    Route::put('comisiones/config/subcategorias/{id_subcategoria}', [ComisionConfigController::class, 'actualizarSubcategoria']);

    Route::get('comisiones/periodos', [ComisionPeriodoController::class, 'index']);
    Route::post('comisiones/periodos/{id}/cerrar', [ComisionPeriodoController::class, 'cerrar']);

    Route::post('comisiones/liquidaciones/{id}/pagar', [ComisionLiquidacionController::class, 'pagar']);

    Route::get('comisiones/movimientos', [ComisionReporteController::class, 'movimientos']);
});

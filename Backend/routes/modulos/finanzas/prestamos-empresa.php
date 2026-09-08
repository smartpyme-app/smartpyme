<?php

use App\Http\Controllers\Api\Finanzas\PrestamosEmpresaController;
use Illuminate\Support\Facades\Route;

Route::middleware(['verificar.funcionalidad:prestamos-empresa', 'verificar.funcionalidad:contabilidad'])->group(function () {
    Route::get('prestamos-empresa', [PrestamosEmpresaController::class, 'index'])->middleware('permission:finanzas.prestamos.ver');
    Route::post('prestamos-empresa/preview', [PrestamosEmpresaController::class, 'preview'])->middleware('permission:finanzas.prestamos.crear');
    Route::post('prestamos-empresa', [PrestamosEmpresaController::class, 'store'])->middleware('permission:finanzas.prestamos.crear');
    Route::get('prestamos-empresa/{id}', [PrestamosEmpresaController::class, 'show'])->middleware('permission:finanzas.prestamos.ver');
    Route::post('prestamos-empresa/{id}/cuotas', [PrestamosEmpresaController::class, 'updateCuotas'])->middleware('permission:finanzas.prestamos.crear');
    Route::post('prestamos-empresa/{id}/pagos', [PrestamosEmpresaController::class, 'pagar'])->middleware('permission:finanzas.prestamos.pagar');
    Route::post('prestamos-empresa/{id}/pagos/anular-ultimo', [PrestamosEmpresaController::class, 'anularUltimoPago'])->middleware('permission:finanzas.prestamos.pagar');
});

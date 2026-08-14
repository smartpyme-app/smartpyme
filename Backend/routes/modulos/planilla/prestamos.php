<?php

use App\Http\Controllers\Api\Planilla\PrestamosController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'planillas', 'middleware' => ['auth:api']], function () {
    Route::controller(PrestamosController::class)->group(function () {
        Route::get('prestamos', 'index')->middleware('permission:planilla.registros.ver');
        Route::get('prestamos/estado-cuenta', 'estadoCuenta')->middleware('permission:planilla.registros.ver');
        Route::get('prestamos/empleado/{id}/prestamos-activos', 'prestamosActivosPorEmpleado')->middleware('permission:planilla.registros.ver');
        Route::post('prestamos', 'store')->middleware('permission:planilla.registros.crear');
        Route::post('prestamos/abono', 'abono')->middleware('permission:planilla.registros.editar');
    });
});

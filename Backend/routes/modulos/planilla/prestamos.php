<?php

use App\Http\Controllers\Api\Planilla\PrestamosController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'planillas', 'middleware' => ['auth:api']], function () {
    Route::controller(PrestamosController::class)->group(function () {
        Route::get('prestamos', 'index');
        Route::get('prestamos/estado-cuenta', 'estadoCuenta');
        Route::get('prestamos/empleado/{id}/prestamos-activos', 'prestamosActivosPorEmpleado');
        Route::post('prestamos', 'store');
        Route::post('prestamos/abono', 'abono');
    });
});

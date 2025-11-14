<?php

use App\Http\Controllers\Api\Contabilidad\Catalogo\CuentasController;
//Route
use Illuminate\Support\Facades\Route;

    Route::get('/catalogo/cuentas',             [CuentasController::class, 'index']);
    Route::get('/catalogo/list',                [CuentasController::class, 'list']);
    Route::post('/catalogo/cuenta',             [CuentasController::class, 'store']);
    Route::get('/catalogo/cuenta/{id}',         [CuentasController::class, 'read']);
    Route::delete('/catalogo/cuenta/{id}',      [CuentasController::class, 'delete']);

    Route::post('/catalogo-cuentas/importar',   [CuentasController::class, 'importCuentas']);
    Route::get('/catalogo-cuentas/plantilla',   [CuentasController::class, 'downloadPlantilla']);


?>

<?php 

use App\Http\Controllers\Api\Contabilidad\Catalogo\CuentasController;
use App\Http\Controllers\Api\Contabilidad\Catalogo\SaldosController;

    Route::get('/catalogo-cuentas',             [CuentasController::class, 'index']);
    Route::post('/catalogo-cuenta',             [CuentasController::class, 'store']);
    Route::get('/catalogo-cuenta/{id}',         [CuentasController::class, 'read']);
    Route::delete('/catalogo-cuenta/{id}',      [CuentasController::class, 'delete']);

    Route::post('/catalogo-cuenta/saldo',             [SaldosController::class, 'store']);
    Route::get('/catalogo-cuenta/saldo/{id}',         [SaldosController::class, 'read']);
    Route::delete('/catalogo-cuenta/saldo/{id}',      [SaldosController::class, 'delete']);


?>

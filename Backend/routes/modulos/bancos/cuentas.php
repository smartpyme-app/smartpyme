<?php

    use App\Http\Controllers\Api\Bancos\CuentasController;

    Route::get('/bancos/cuentas',              [CuentasController::class, 'index']);
    Route::get('/bancos/cuenta/{id}',          [CuentasController::class, 'read']);
    Route::get('/bancos/cuentas/list',         [CuentasController::class, 'list']);
    Route::post('/bancos/cuenta',              [CuentasController::class, 'store']);
    Route::delete('/bancos/cuenta/{id}',       [CuentasController::class, 'delete']);

?>

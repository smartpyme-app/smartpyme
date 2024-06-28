<?php

    use App\Http\Controllers\Api\Bancos\CuentasController;

    Route::get('/bancos/cuentas',              [CuentasController::class, 'index']);
    Route::get('/banco/cuenta/{id}',          [CuentasController::class, 'read']);
    Route::get('/banco/cuentas/list',         [CuentasController::class, 'list']);
    Route::post('/banco/cuenta',              [CuentasController::class, 'store']);
    Route::delete('/banco/cuenta/{id}',       [CuentasController::class, 'delete']);

    Route::get('/banco/cuenta/libro/{id}/{del}/{al}', [CuentasController::class, 'libro']);

?>

<?php

use App\Http\Controllers\Api\MhDteProxyController;
use Illuminate\Support\Facades\Route;

Route::post('/dte/enviar', [MhDteProxyController::class, 'enviar']);
Route::post('/dte/consultar', [MhDteProxyController::class, 'consultar']);
Route::post('/dte/contingencia', [MhDteProxyController::class, 'contingencia']);
Route::post('/dte/anular', [MhDteProxyController::class, 'anular']);
Route::post('/dte/auth-test', [MhDteProxyController::class, 'authTest']);

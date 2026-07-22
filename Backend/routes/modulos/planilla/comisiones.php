<?php

use App\Http\Controllers\Api\Planilla\ComisionesController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'planillas', 'middleware' => ['auth:api']], function () {
    Route::controller(ComisionesController::class)->group(function () {
        Route::get('comisiones', 'index');
        Route::get('comisiones/summary', 'summary');
        Route::post('comisiones', 'store');
        Route::get('comisiones/{id}', 'show');
        Route::put('comisiones/{id}', 'update');
        Route::delete('comisiones/{id}', 'destroy');
    });
});

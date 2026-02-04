<?php

use App\Http\Controllers\Api\Admin\EmpresasController;
use App\Http\Controllers\Api\Admin\SuscripcionesController;
use Barryvdh\DomPDF\PDF;
use Illuminate\Support\Facades\Route;

    Route::get('/suscripciones',               [SuscripcionesController::class, 'index']);
    Route::get('/suscripciones/list',          [SuscripcionesController::class, 'list']);
    Route::get('/suscripciones/exportar',      [SuscripcionesController::class, 'export']);
    Route::get('/suscripciones/campanias',     [SuscripcionesController::class, 'getCampanias']);
    Route::post('/suscripcion/create',         [SuscripcionesController::class, 'createSuscription']);
    Route::post('/suscripcion/edit',           [SuscripcionesController::class, 'editSuscription']);
    Route::get('/suscripcion/{id}',            [SuscripcionesController::class, 'read']);
    Route::post('/suscripcion/cancel',         [SuscripcionesController::class, 'cancelSuscription']);
    Route::delete('/suscripcion/{id}',         [SuscripcionesController::class, 'delete']);
    Route::post('/suscripcion/getUsersSelect', [SuscripcionesController::class, 'getUsersSelect']);
    Route::post('/suscripcion/activar',        [SuscripcionesController::class, 'activateSystem']);
    Route::post('/suscripcion/suspender',      [SuscripcionesController::class, 'suspendSystem']);
    Route::get('/suscripciones/{id}/pagos',    [SuscripcionesController::class, 'getHistorialPagos']);
    Route::get('/suscripcion/{id}/recibo-suscripcion',     [EmpresasController::class, 'printReciboSuscripcion']);
    Route::post('/suscripcion/pago-recurrente',     [EmpresasController::class, 'updatePagoRecurrente']);


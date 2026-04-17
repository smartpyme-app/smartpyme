<?php

use App\Http\Controllers\Api\Admin\EmpresasController;
use App\Http\Controllers\Api\Admin\SuscripcionesController;
use App\Http\Controllers\Api\PromocionalesController;
use Barryvdh\DomPDF\PDF;
use Illuminate\Support\Facades\Route;

    Route::get('/suscripciones',               [SuscripcionesController::class, 'index']);
    Route::get('/suscripciones/list',          [SuscripcionesController::class, 'list']);
    Route::get('/suscripciones/exportar',      [SuscripcionesController::class, 'export']);
    Route::get('/suscripciones/campanias',     [SuscripcionesController::class, 'getCampanias']);
    Route::get('/promocionales',               [PromocionalesController::class, 'index']);
    Route::get('/promocionales/list',          [PromocionalesController::class, 'list']);
    Route::get('/promocional/{id}',            [PromocionalesController::class, 'read']);
    Route::post('/promocional/create',         [PromocionalesController::class, 'store']);
    Route::post('/promocional/edit',           [PromocionalesController::class, 'update']);
    Route::delete('/promocional/{id}',         [PromocionalesController::class, 'delete']);
    Route::post('/suscripcion/create',         [SuscripcionesController::class, 'createSuscription']);
    Route::post('/suscripcion/edit',           [SuscripcionesController::class, 'editSuscription']);
    Route::post('/suscripcion/pago-recibido',   [SuscripcionesController::class, 'registrarPagoRecibido']);
    Route::post('/suscripcion/acceso-temporal', [SuscripcionesController::class, 'concederAccesoTemporal']);
    Route::post('/suscripcion/cancelar-acceso-temporal', [SuscripcionesController::class, 'cancelarAccesoTemporal']);
    Route::get('/suscripcion/{id}',            [SuscripcionesController::class, 'read']);
    Route::post('/suscripcion/cancel',         [SuscripcionesController::class, 'cancelSuscription']);
    Route::delete('/suscripcion/{id}',         [SuscripcionesController::class, 'delete']);
    Route::post('/suscripcion/getUsersSelect', [SuscripcionesController::class, 'getUsersSelect']);
    Route::post('/suscripcion/activar',        [SuscripcionesController::class, 'activateSystem']);
    Route::post('/suscripcion/suspender',      [SuscripcionesController::class, 'suspendSystem']);
    Route::get('/suscripciones/{id}/pagos',    [SuscripcionesController::class, 'getHistorialPagos']);
    Route::get('/suscripcion/{id}/recibo-suscripcion',     [EmpresasController::class, 'printReciboSuscripcion']);
    Route::post('/suscripcion/pago-recurrente',     [EmpresasController::class, 'updatePagoRecurrente']);

?>

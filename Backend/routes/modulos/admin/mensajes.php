<?php 
use App\Http\Controllers\Api\Admin\MensajesController;

    Route::get('/mensajes',                        [MensajesController::class, 'index']);
    Route::get('/mensajes/buscar/{txt}',           [MensajesController::class, 'search']);
    Route::get('/mensajes/filtrar/{filtro}/{text}', [MensajesController::class, 'filter']);
    Route::post('/mensaje',                        [MensajesController::class, 'store']);
    Route::get('/mensaje/{id}',                    [MensajesController::class, 'read']);
    Route::delete('/mensaje/{id}',                 [MensajesController::class, 'delete']);

?> 
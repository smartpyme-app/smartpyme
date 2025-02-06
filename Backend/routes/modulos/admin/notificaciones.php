<?php 

use App\Http\Controllers\Api\Admin\NotificacionesController;

    Route::get('/notificaciones',                  [NotificacionesController::class, 'index']);
    Route::get('/notificacion/{id}',              [NotificacionesController::class, 'read']);
    Route::post('/notificacion',                  [NotificacionesController::class, 'store']);
    Route::delete('/notificacion/{id}',           [NotificacionesController::class, 'delete']);

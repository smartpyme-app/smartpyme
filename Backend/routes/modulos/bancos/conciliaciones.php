<?php

    use App\Http\Controllers\Api\Bancos\ConciliacionesController;

    Route::get('/bancos/conciliaciones',            [ConciliacionesController::class, 'index']);
    Route::get('/banco/conciliacion/{id}',          [ConciliacionesController::class, 'read']);
    Route::get('/banco/conciliaciones/list',        [ConciliacionesController::class, 'list']);
    Route::post('/banco/conciliacion',              [ConciliacionesController::class, 'store']);
    Route::delete('/banco/conciliacion/{id}',       [ConciliacionesController::class, 'delete']);

?>

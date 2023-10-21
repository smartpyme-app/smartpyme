<?php

    use App\Http\Controllers\Api\Creditos\FiadoresController;

    Route::get('/credito/fiador/{id}',      [FiadoresController::class, 'read']);
    Route::post('/credito/fiador',          [FiadoresController::class, 'store']);
    Route::delete('/credito/fiador/{id}',   [FiadoresController::class, 'delete']);

?>
<?php

    use App\Http\Controllers\Api\Eventos\DetallesController;

    Route::post('/evento/detalle',          [DetallesController::class, 'store']);
    Route::delete('/evento/detalle/{id}',   [DetallesController::class, 'delete']);

?>

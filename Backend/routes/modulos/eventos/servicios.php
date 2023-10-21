<?php 
    
    use App\Http\Controllers\Api\Eventos\ServiciosController;

    Route::get('/servicios',                    [ServiciosController::class, 'index']);
    Route::get('/servicios/list',               [ServiciosController::class, 'list']);
    Route::get('/servicio/{id}',                [ServiciosController::class, 'read']);
    Route::get('/servicios/buscar/{text}',      [ServiciosController::class, 'search']);
    Route::get('/servicios/filtrar/{filtro}/{valor}', [ServiciosController::class, 'filter']);
    Route::post('/servicio',                    [ServiciosController::class, 'store']);
    Route::delete('/servicio/{id}',             [ServiciosController::class, 'delete']);

?>
<?php

    use App\Http\Controllers\Api\Creditos\CreditosController;

    Route::get('/creditos',                    [CreditosController::class, 'index']);
    Route::get('/credito/{id}',                [CreditosController::class, 'read']);
    Route::get('/creditos/buscar/{text}',      [CreditosController::class, 'search']);
    Route::post('/creditos/filtrar',           [CreditosController::class, 'filter']);
    Route::post('/credito',                    [CreditosController::class, 'store']);
    Route::delete('/credito/{id}',             [CreditosController::class, 'delete']);

    Route::get('/reporte/credito/plan-de-pagos/{monto}/{plazo}/{tipo_plazo}/{interes}/{id?}',        [CreditosController::class, 'planDePagos']);
    Route::get('/reporte/credito/pagos/{id}',        [CreditosController::class, 'imprimirPagos']);
    Route::get('/reporte/credito/pago/{id}',        [CreditosController::class, 'imprimirPago']);

?>

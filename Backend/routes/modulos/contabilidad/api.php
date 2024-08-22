<?php

use App\Http\Controllers\Api\Contabilidad\ApiController;

    Route::post('/contabilidad/partida/venta',  [ApiController::class, 'venta']);
    Route::post('/contabilidad/partida/compra',  [ApiController::class, 'compra']);
    Route::post('/contabilidad/partida/transaccion',  [ApiController::class, 'transaccion']);

?>

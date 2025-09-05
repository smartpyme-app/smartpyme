<?php

use App\Http\Controllers\Api\Contabilidad\ApiController;
use Illuminate\Support\Facades\Route;

    Route::post('/contabilidad/partida/venta',  [ApiController::class, 'venta']);
    Route::post('/contabilidad/partida/compra',  [ApiController::class, 'compra']);
    Route::post('/contabilidad/partida/gasto',  [ApiController::class, 'gasto']);
    Route::post('/contabilidad/partida/transaccion',  [ApiController::class, 'transaccion']);
    Route::post('/contabilidad/partida/cxp',  [ApiController::class, 'cxp']);
    Route::post('/contabilidad/partida/cxc',  [ApiController::class, 'cxc']);
    Route::post('/contabilidad/partida/retaceo',  [ApiController::class, 'retaceo']);
    Route::post('/contabilidad/partida/ajuste',  [ApiController::class, 'ajuste']);
    Route::post('/contabilidad/partida/traslado',  [ApiController::class, 'traslado']);
    Route::post('/contabilidad/partida/otra-entrada',  [ApiController::class, 'otraEntrada']);
    Route::post('/contabilidad/partida/otra-salida',  [ApiController::class, 'otraSalida']);

?>

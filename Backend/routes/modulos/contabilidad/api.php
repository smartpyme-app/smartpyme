<?php

use App\Http\Controllers\Api\Contabilidad\ApiController;
use App\Http\Controllers\Api\Contabilidad\LibrosIVAController;

    Route::post('/contabilidad/partida/venta',  [ApiController::class, 'venta']);
    Route::post('/contabilidad/partida/compra',  [ApiController::class, 'compra']);
    Route::post('/contabilidad/partida/gasto',  [ApiController::class, 'gasto']);
    Route::post('/contabilidad/partida/transaccion',  [ApiController::class, 'transaccion']);


    Route::get('/libro-iva/consumidores',           [LibrosIVAController::class, 'consumidores']);
    Route::get('/libro-iva/contribuyentes',         [LibrosIVAController::class, 'contribuyentes']);
    Route::get('/libro-iva/compras',         [LibrosIVAController::class, 'compras']);

    Route::get('/libro-iva/compras/descargar-libro',         [LibrosIVAController::class, 'comprasLibroExport']);
    Route::get('/libro-iva/compras/descargar-anexo',         [LibrosIVAController::class, 'comprasAnexoExport']);

?>

<?php 

use App\Http\Controllers\Api\Contabilidad\LibrosIVAController;

    Route::get('/libro-iva/consumidores',           [LibrosIVAController::class, 'consumidores']);
    Route::get('/libro-iva/consumidores/descargar-libro',         [LibrosIVAController::class, 'consumidoresLibroExport']);
    Route::get('/libro-iva/consumidores/descargar-anexo',         [LibrosIVAController::class, 'consumidoresAnexoExport']);
    
    Route::get('/libro-iva/contribuyentes',         [LibrosIVAController::class, 'contribuyentes']);
    Route::get('/libro-iva/contribuyentes/descargar-libro',         [LibrosIVAController::class, 'contribuyentesLibroExport']);
    Route::get('/libro-iva/contribuyentes/descargar-anexo',         [LibrosIVAController::class, 'contribuyentesAnexoExport']);
    
    Route::get('/libro-iva/compras',         [LibrosIVAController::class, 'compras']);
    Route::get('/libro-iva/compras/descargar-libro',         [LibrosIVAController::class, 'comprasLibroExport']);
    Route::get('/libro-iva/compras/descargar-anexo',         [LibrosIVAController::class, 'comprasAnexoExport']);


?>



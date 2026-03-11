<?php 

use App\Http\Controllers\Api\Contabilidad\LibrosIVAController;
//use Route;
use Illuminate\Support\Facades\Route;

    Route::get('/libro-iva/consumidores',           [LibrosIVAController::class, 'consumidores']);
    Route::get('/libro-iva/consumidores/descargar-libro',         [LibrosIVAController::class, 'consumidoresLibroExport']);
    Route::get('/libro-iva/consumidores/descargar-anexo',         [LibrosIVAController::class, 'consumidoresAnexoExport']);
    Route::get('/libro-iva/consumidores/descargar-dttes',         [LibrosIVAController::class, 'GlobalDttesExport']);
    
    Route::get('/libro-iva/contribuyentes',         [LibrosIVAController::class, 'contribuyentes']);
    Route::get('/libro-iva/contribuyentes/descargar-libro',         [LibrosIVAController::class, 'contribuyentesLibroExport']);
    Route::get('/libro-iva/contribuyentes/descargar-anexo',         [LibrosIVAController::class, 'contribuyentesAnexoExport']);
    Route::get('/libro-iva/contribuyentes/descargar-dttes',         [LibrosIVAController::class, 'GlobalDttesExport']);
    Route::get('/libro-iva/contribuyentes/descargar-notas-credito-debito', [LibrosIVAController::class, 'notasCreditoDebitoExport']);
    
    Route::get('/libro-iva/compras',                        [LibrosIVAController::class, 'compras']);
    Route::get('/libro-iva/compras/descargar-libro',         [LibrosIVAController::class, 'comprasLibroExport']);
    Route::get('/libro-iva/compras/descargar-anexo',         [LibrosIVAController::class, 'comprasAnexoExport']);
    
    Route::get('/libro-iva/anulados',                        [LibrosIVAController::class, 'anulados']);
    Route::get('/libro-iva/anulados/descargar-libro',         [LibrosIVAController::class, 'anuladosLibroExport']);
    Route::get('/libro-iva/anulados/descargar-anexo',         [LibrosIVAController::class, 'anuladosAnexoExport']);
    
    Route::get('/libro-iva/compras-sujetos-excluidos',                        [LibrosIVAController::class, 'comprasSujetosExcluidos']);
    Route::get('/libro-iva/compras-sujetos-excluidos/descargar-libro',         [LibrosIVAController::class, 'comprasSujetosExcluidosLibroExport']);
    Route::get('/libro-iva/compras-sujetos-excluidos/descargar-anexo',         [LibrosIVAController::class, 'comprasSujetosExcluidosAnexoExport']);
    
    Route::get('/libro-iva/retencion1/descargar-libro',         [LibrosIVAController::class, 'libroRetencion1Export']);
    Route::get('/libro-iva/retencion1/descargar-anexo',         [LibrosIVAController::class, 'anexoRetencion1Export']);
    Route::get('/libro-iva/percepcion1/descargar-libro',         [LibrosIVAController::class, 'libroPercepcion1Export']);
    Route::get('/libro-iva/percepcion1/descargar-anexo',         [LibrosIVAController::class, 'anexoPercepcion1Export']);


?>



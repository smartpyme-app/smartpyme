<?php 

use App\Http\Controllers\Api\Admin\BodegasController;
//use Route;
use Illuminate\Support\Facades\Route;

    Route::get('/bodegas',                  [BodegasController::class, 'index']);
    Route::get('/bodega/{id}',              [BodegasController::class, 'read']);
    Route::post('/bodega',                  [BodegasController::class, 'store']);
    Route::get('/reporte/bodegas/{id}/{cat}', [BodegasController::class, 'reporte']);
    Route::delete('/bodega/{id}',              [BodegasController::class, 'delete']);
    

?>
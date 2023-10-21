<?php 

use App\Http\Controllers\Api\Compras\DetallesController;

Route::get('/compra/detalle/{id}',     [DetallesController::class, 'read']);
Route::post('/compra/detalle',         [DetallesController::class, 'store']);
Route::delete('/compra/detalle/{id}',  [DetallesController::class, 'delete']);

Route::post('/compras/detalle',          [DetallesController::class, 'historial']);
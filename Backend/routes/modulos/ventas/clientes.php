<?php 

use App\Http\Controllers\Api\Ventas\Clientes\ClientesController;
use App\Http\Controllers\Api\Ventas\Clientes\DocumentosController;
use App\Http\Controllers\Api\Ventas\VentasController;

    Route::get('/clientes',                         [ClientesController::class, 'index']);
    Route::get('/clientes/list',                    [ClientesController::class, 'list']);
    Route::get('/cliente/{id}',                     [ClientesController::class, 'read']);
    Route::post('/cliente',                         [ClientesController::class, 'store']);
    Route::delete('/cliente/{id}',                  [ClientesController::class, 'delete']);
    Route::post('/cliente/datos',                   [ClientesController::class, 'datos']);

    Route::post('/clientes/dash',                   [ClientesController::class, 'dash']);
    Route::get('/cliente/estado-de-cuenta/{id}',  [ClientesController::class, 'estadoCuenta']);
    
// Otros
    Route::get('/cliente/ventas/{id}',              [ClientesController::class, 'ventas']);
    Route::post('/cliente/ventas/filtrar',          [ClientesController::class, 'ventasFilter']);
    Route::get('/cliente/vales-pendientes/{id}',    [ClientesController::class, 'valesPendientes']);
    Route::get('/cliente/anticipos/{id}',           [ClientesController::class, 'anticipos']);
    Route::get('/cliente/creditos/{id}',            [ClientesController::class, 'creditos']);

    Route::get('/cuentas-cobrar',                  [VentasController::class, 'cxc']);
    Route::get('/cuentas-cobrar/buscar/{text}',    [VentasController::class, 'cxcBuscar']);

    Route::get('/cliente/{id}/documentos',           [DocumentosController::class, 'index']);
    Route::get('/cliente/documento/{id}',           [DocumentosController::class, 'read']);
    Route::post('/cliente/documento',                [DocumentosController::class, 'store']);
    Route::delete('/cliente/documento/{id}',         [DocumentosController::class, 'delete']);

    Route::post('/clientes/importar',          [ClientesController::class, 'import']);
    Route::get('/clientes/exportar',    [ClientesController::class, 'export']);

?>

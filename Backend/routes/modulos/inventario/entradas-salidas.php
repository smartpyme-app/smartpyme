<?php

use App\Http\Controllers\Api\Inventario\Entradas\EntradasController;
use App\Http\Controllers\Api\Inventario\Entradas\DetallesController as EDController;
use App\Http\Controllers\Api\Inventario\Salidas\SalidasController;
use App\Http\Controllers\Api\Inventario\Salidas\DetallesController as SDController;
use App\Support\PermisoAnular;

$pVerInv = config('permissions.PERMISSION_PRODUCTOS.inventario.ver');
$pInvCrear = config('permissions.PERMISSION_PRODUCTOS.inventario.crear');
$pInvEditar = config('permissions.PERMISSION_PRODUCTOS.inventario.editar');
$pInvEliminar = PermisoAnular::eliminarOAnular(config('permissions.PERMISSION_PRODUCTOS.inventario.eliminar'));
$pVerOtraEntrada = config('permissions.PERMISSION_PRODUCTOS.otras_entradas.ver');
$pCrearOtraEntrada = config('permissions.PERMISSION_PRODUCTOS.otras_entradas.crear');
$pEditOtraEntrada = config('permissions.PERMISSION_PRODUCTOS.otras_entradas.editar');
$pVerOtraSalida = config('permissions.PERMISSION_PRODUCTOS.otras_salidas.ver');
$pCrearOtraSalida = config('permissions.PERMISSION_PRODUCTOS.otras_salidas.crear');
$pEditOtraSalida = config('permissions.PERMISSION_PRODUCTOS.otras_salidas.editar');
$verListaEntradas = $pVerOtraEntrada.'|'.$pVerInv;
$verListaSalidas = $pVerOtraSalida.'|'.$pVerInv;
$storeEntrada = $pCrearOtraEntrada.'|'.$pInvCrear;
$storeSalida = $pCrearOtraSalida.'|'.$pInvCrear;
$detalleEntrada = $pCrearOtraEntrada.'|'.$pEditOtraEntrada.'|'.$pInvCrear.'|'.$pInvEditar;
$detalleSalida = $pCrearOtraSalida.'|'.$pEditOtraSalida.'|'.$pInvCrear.'|'.$pInvEditar;
$eliminarDetalleEntrada = $pEditOtraEntrada.'|'.$pInvEditar.'|'.$pInvEliminar;
$eliminarDetalleSalida = $pEditOtraSalida.'|'.$pInvEditar.'|'.$pInvEliminar;
$aprobarAnularPartidaEntrada = $pEditOtraEntrada.'|'.$pInvEditar;
$aprobarAnularPartidaSalida = $pEditOtraSalida.'|'.$pInvEditar;

// Entradas
    Route::get('/entradas',                [EntradasController::class, 'index'])->middleware('permission:'.$verListaEntradas);
    Route::get('/entrada/{id}',            [EntradasController::class, 'read'])->middleware('permission:'.$verListaEntradas);
    Route::get('/entradas/buscar/{txt}',   [EntradasController::class, 'search'])->middleware('permission:'.$verListaEntradas);
    Route::post('/entradas/filtrar',       [EntradasController::class, 'filter'])->middleware('permission:'.$verListaEntradas);
    Route::post('/entrada',                [EntradasController::class, 'store'])->middleware('permission:'.$storeEntrada);
    Route::delete('/entrada/{id}',         [EntradasController::class, 'delete'])->middleware('permission:'.$pInvEliminar);
    Route::post('/entrada/aprobar/{id}',   [EntradasController::class, 'aprobar'])->middleware('permission:'.$aprobarAnularPartidaEntrada);
    Route::post('/entrada/anular/{id}',    [EntradasController::class, 'anular'])->middleware('permission:'.$aprobarAnularPartidaEntrada);
    Route::get('/reporte/entrada/{id}',    [EntradasController::class, 'generarDoc'])->middleware('permission:'.$verListaEntradas);
    Route::post('/entrada/partida-contable/{id}', [EntradasController::class, 'generarPartidaContable'])->middleware('permission:'.$aprobarAnularPartidaEntrada);

// Detalles
    Route::get('/entrada/detalles',        [EDController::class, 'index'])->middleware('permission:'.$verListaEntradas);
    Route::get('/entrada/detalle/{id}',    [EDController::class, 'read'])->middleware('permission:'.$verListaEntradas);
    Route::post('/entrada/detalle',        [EDController::class, 'store'])->middleware('permission:'.$detalleEntrada);
    Route::delete('/entrada/detalle/{id}', [EDController::class, 'delete'])->middleware('permission:'.$eliminarDetalleEntrada);

// Salidas
    Route::get('/salidas',                [SalidasController::class, 'index'])->middleware('permission:'.$verListaSalidas);
    Route::get('/salida/{id}',            [SalidasController::class, 'read'])->middleware('permission:'.$verListaSalidas);
    Route::get('/salidas/buscar/{txt}',   [SalidasController::class, 'search'])->middleware('permission:'.$verListaSalidas);
    Route::post('/salidas/filtrar',       [SalidasController::class, 'filter'])->middleware('permission:'.$verListaSalidas);
    Route::post('/salida',                [SalidasController::class, 'store'])->middleware('permission:'.$storeSalida);
    Route::delete('/salida/{id}',         [SalidasController::class, 'delete'])->middleware('permission:'.$pInvEliminar);
    Route::post('/salida/aprobar/{id}',   [SalidasController::class, 'aprobar'])->middleware('permission:'.$aprobarAnularPartidaSalida);
    Route::post('/salida/anular/{id}',    [SalidasController::class, 'anular'])->middleware('permission:'.$aprobarAnularPartidaSalida);
    Route::get('/reporte/salida/{id}',    [SalidasController::class, 'generarDoc'])->middleware('permission:'.$verListaSalidas);
    Route::post('/salida/partida-contable/{id}', [SalidasController::class, 'generarPartidaContable'])->middleware('permission:'.$aprobarAnularPartidaSalida);

// Detalles
    Route::get('/salida/detalles',        [SDController::class, 'index'])->middleware('permission:'.$verListaSalidas);
    Route::get('/salida/detalle/{id}',    [SDController::class, 'read'])->middleware('permission:'.$verListaSalidas);
    Route::post('/salida/detalle',        [SDController::class, 'store'])->middleware('permission:'.$detalleSalida);
    Route::delete('/salida/detalle/{id}', [SDController::class, 'delete'])->middleware('permission:'.$eliminarDetalleSalida);


?>

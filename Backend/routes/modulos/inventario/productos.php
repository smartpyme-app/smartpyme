<?php

use App\Http\Controllers\Api\Inventario\ProductosController;
use App\Http\Controllers\Api\Inventario\ConsignasController;
use App\Http\Controllers\Api\Inventario\Composiciones\ComposicionesController;
use App\Http\Controllers\Api\Inventario\Composiciones\OpcionesController;
use App\Http\Controllers\Api\Inventario\PreciosController;
use App\Http\Controllers\Api\Inventario\PromocionesController;
use App\Http\Controllers\Api\Inventario\ImagenesController;
use App\Http\Controllers\Api\Inventario\ProveedorController;
use App\Http\Controllers\Api\Inventario\KardexController;
use App\Http\Controllers\Api\Inventario\SucursalesController;
//use Route;
use App\Http\Controllers\Api\Webhook\WooCommerceController;
use Illuminate\Support\Facades\Route;

    Route::get('/productos',         		    [ProductosController::class, 'index']);
    Route::get('/producto/{id}',     		    [ProductosController::class, 'read']);
    Route::get('/productos/list',               [ProductosController::class, 'list']);
    Route::get('/productos/buscar/{txt}',       [ProductosController::class, 'search']);
    Route::get('/productos/buscar-by-query',    [ProductosController::class, 'searchByQuery']);
    Route::get('/productos-all/buscar/{text}',  [ProductosController::class, 'searchAll']);
    Route::post('/producto',                    [ProductosController::class, 'store']);
    Route::delete('/producto/{id}',  		    [ProductosController::class, 'delete']);

    Route::post('/compra/guardar-producto', [ProductosController::class, 'storeDesdeCompras']);

    Route::get('/productos/buscar-codigo/{codigo}', [ProductosController::class, 'porCodigo']);

    Route::get('/productos/kardex',  	        [KardexController::class, 'index']);
    Route::get('/productos/kardex/exportar',    [KardexController::class, 'export']);

    Route::post('/productos/analisis',          [ProductosController::class, 'analisis']);
    Route::get('/producto/precios/historicos/{id}', [ProductosController::class, 'precios']);

// Composisiones
    Route::post('/producto/composicion',        [ComposicionesController::class, 'store']);
    Route::delete('/producto/composicion/{id}', [ComposicionesController::class, 'delete']);

    Route::post('/producto/composicion/opcion',        [OpcionesController::class, 'store']);
    Route::delete('/producto/composicion/opcion/{id}', [OpcionesController::class, 'delete']);

// Precios
    Route::post('/producto/precio',        [PreciosController::class, 'store']);
    Route::delete('/producto/precio/{id}', [PreciosController::class, 'delete']);
    Route::post('/precios/importar',          [PreciosController::class, 'import']);

// Proveedor
    Route::post('/producto/proveedor',        [ProveedorController::class, 'store']);
    Route::delete('/producto/proveedor/{id}', [ProveedorController::class, 'delete']);


// Sucursales
    Route::get('/producto/sucursales/{id}',    [SucursalesController::class, 'index']);
    Route::post('/producto/sucursal',          [SucursalesController::class, 'store']);
    Route::delete('/producto/sucursal/{id}',   [SucursalesController::class, 'delete']);

// Consignas
    Route::get('/productos/consignas',         [ConsignasController::class, 'index']);
    Route::post('/producto/sucursal',          [ConsignasController::class, 'store']);
    Route::delete('/producto/sucursal/{id}',   [ConsignasController::class, 'delete']);
    Route::get('/productos/consignas/exportar',        [ConsignasController::class, 'export']);

// Promociones
    Route::get('promociones',        [PromocionesController::class, 'index']);
    Route::post('promocion',          [PromocionesController::class, 'store']);
    Route::delete('promocion/{id}',   [PromocionesController::class, 'delete']);
    Route::get('promociones/eliminar',   [PromocionesController::class, 'deleteAll']);

// Imagenes
    Route::post('/producto/imagen',        [ImagenesController::class, 'store']);
    Route::delete('/producto/imagen/{id}', [ImagenesController::class, 'delete']);

    Route::get('/producto/compras/{id}',          [ProductosController::class, 'compras']);
    Route::get('/producto/ajustes/{id}',          [ProductosController::class, 'ajustes']);
    Route::get('/producto/ventas/{id}',          [ProductosController::class, 'ventas']);

    Route::post('/productos/importar',          [ProductosController::class, 'import']);
    Route::get('/productos/exportar',          [ProductosController::class, 'export']);
    //exportar-plantilla
    Route::get('/productos/exportar-plantilla', [ProductosController::class, 'exportarPlantilla']);
    //productos/ajuste-masivo/importar
    Route::post('/productos/ajuste-masivo/importar', [ProductosController::class, 'importarAjustes']);
    //productos/ajuste-masivo
    Route::post('/productos/ajuste-masivo', [ProductosController::class, 'ajusteMasivo']);

    Route::post('/producto/exportar-woocommerce',          [WooCommerceController::class, 'exportarWooCommerce']);
    //productos/exportar/woocommerce
    Route::get('/productos/exportar/woocommerce',          [ProductosController::class, 'exportarWooCommerceTemplate']);
    //productos/exportar-traslado
    Route::get('/productos/exportar-traslado',          [ProductosController::class, 'exportarPlantillaTraslado']);
    //productos/traslado-masivo/importar
    Route::post('/productos/traslado-masivo/importar',          [ProductosController::class, 'importarTrasladosMasivos']);
    //productos/traslado-masivo post
    Route::post('/productos/traslado-masivo',          [ProductosController::class, 'trasladoMasivo']);
?>

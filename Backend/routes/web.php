<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

use App\Http\Controllers\HomeController;

Route::get('/',       			[HomeController::class, 'index'])->name('home');
Route::post('/demo',       		[HomeController::class, 'demoPost'])->name('demo');

Route::get('/clear-bd', function(){

	\App\Models\Inventario\Producto::truncate();
	\App\Models\Inventario\Inventario::truncate();
	\App\Models\Inventario\Sucursal::truncate();
	\App\Models\Inventario\Categorias\Categoria::truncate();
	\App\Models\Inventario\Categorias\SubCategoria::truncate();
	\App\Models\Ventas\Clientes\Cliente::truncate();
	\App\Models\Compras\Proveedores\Proveedor::truncate();
    \App\Models\Transporte\Mantenimientos\Mantenimiento::truncate();
    \App\Models\Transporte\Mantenimientos\Detalle::truncate();
    \App\Models\Empleados\Empleados\Empleado::truncate();
    \App\Models\Transporte\Fletes\Flete::truncate();
    \App\Models\Transporte\Fletes\Detalle::truncate();
    \App\Models\Transporte\Flotas\Flota::truncate();
	return "Listo XD";
	
})->name('clearBD');


Auth::routes();

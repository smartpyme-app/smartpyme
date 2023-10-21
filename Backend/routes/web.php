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
Route::get('/registro',       	[HomeController::class, 'registro'])->name('registro');
Route::get('/demo',       		[HomeController::class, 'demo']);
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

Route::get('/set-precioiva', function(){
	$productos = \App\Models\Inventario\Producto::withoutGlobalScope('sucursal')->whereNotIn('id', [2232,2280])->get();
	
	foreach ($productos as $producto) {
		$producto->precio = $producto->precio + ($producto->precio * 0.13);
		if ($producto->precio2) {
			$producto->precio2 = $producto->precio2 + ($producto->precio2 * 0.13);
		}
		if ($producto->precio3) {
			$producto->precio3 = $producto->precio3 + ($producto->precio3 * 0.13);
		}
		if ($producto->precio4) {
			$producto->precio4 = $producto->precio4 + ($producto->precio4 * 0.13);
		}
		$producto->save();
	}

	return "Listo XD";
	
})->name('categoriasSinProductos');

Route::get('/sincategorias', function(){
	$productos = \App\Models\Inventario\Producto::all();
	foreach ($productos as $producto) {
		$subcategoria = \App\Models\Inventario\Categorias\SubCategoria::find($producto->subcategoria_id);
		if ($subcategoria) {
			$producto->categoria_id = $subcategoria->categoria_id;
			$producto->save();
		}
	}
	return "Listo XD";
	
})->name('sincategorias');

Route::prefix('demo-gratis')->group(function () {

	Route::get('/{url1?}/{url2?}/{url3?}', function () { 
		return redirect('/demo-gratis');	
	});

});

Auth::routes();

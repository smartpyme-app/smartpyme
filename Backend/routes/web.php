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
use App\Http\Controllers\Api\Inventario\ProductosController;
use App\Http\Controllers\Auth\AuthJWTController;
use Illuminate\Support\Facades\DB;

Route::get('/pago-wompi', [App\Http\Controllers\WompiController::class, 'pagoWompi'])->name('pagoWompi');

Route::get('/payment/{id}', [AuthJWTController::class, 'pagoCompletado'])->name('payment.n1co');
Route::get('/registro/{id}', [AuthJWTController::class, 'pagoFinish'])->name('payment.finish');

Route::get('/descargar-ticket/{id}', 	[AuthJWTController::class, 'suscription'])->name('suscripcion.ticket');


Route::get('/',       			[HomeController::class, 'index'])->name('home');
Route::post('/demo',       		[HomeController::class, 'demoPost'])->name('demo');

Route::get('/clear-bd', function(){
	
	$id_empresa = 75;
	$id_sucursal = 94; //Biovet
	
	// $productos = DB::table('productos')->where('id_empresa', '!=', $id_empresa)->get();

	// foreach ($productos as $producto) {
		App\Models\Inventario\Inventario::leftJoin('productos', 'inventario.id_producto', '=', 'productos.id')
		    ->whereNull('productos.id')
		    ->select('inventario.*')
		    ->delete();
		App\Models\Inventario\Kardex::leftJoin('productos', 'kardexs.id_producto', '=', 'productos.id')
		    ->whereNull('productos.id')
		    ->select('kardexs.*')
		    ->delete();
	// }
	DB::table('productos')->where('id_empresa', '!=', $id_empresa)->delete();
	DB::table('inventario')->where('id_sucursal', '!=', $id_sucursal)->delete();
	DB::table('sucursales')->where('id_empresa', '!=', $id_empresa)->delete();
	DB::table('categorias')->where('id_empresa', '!=', $id_empresa)->delete();
	// $ventas = DB::table('ventas')->where('id_empresa', '!=', $id_empresa)->get();
	// foreach ($ventas as $venta) {
		App\Models\Ventas\Detalle::leftJoin('ventas', 'detalles_venta.id_venta', '=', 'ventas.id')
		    ->whereNull('ventas.id')
		    ->select('detalles_venta.*')
		    ->delete();
	// }
	DB::table('ventas')->where('id_empresa', '!=', $id_empresa)->delete();
	DB::table('egresos')->where('id_empresa', '!=', $id_empresa)->delete();
	// $compras = DB::table('compras')->where('id_empresa', '!=', $id_empresa)->get();
	// foreach ($compras as $compra) {
		App\Models\Compras\Detalle::leftJoin('compras', 'detalles_compra.id_compra', '=', 'compras.id')
		    ->whereNull('compras.id')
		    ->select('detalles_compra.*')
		    ->delete();
	// }
	DB::table('compras')->where('id_empresa', '!=', $id_empresa)->delete();
	DB::table('clientes')->where('id_empresa', '!=', $id_empresa)->delete();
	DB::table('proveedores')->where('id_empresa', '!=', $id_empresa)->delete();

	return "Listo XD";
	
})->name('clearBD');


Auth::routes();

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


	DB::table('kardexs')->whereYear('created_at', '<', 2024)->delete();
	DB::table('ventas')->whereYear('created_at', '<', 2024)->delete();
	DB::table('detalles_venta')->whereYear('created_at', '<', 2024)->delete();
	DB::table('egresos')->whereYear('created_at', '<', 2024)->delete();
	DB::table('compras')->whereYear('created_at', '<', 2024)->delete();
	DB::table('detalles_compra')->whereYear('created_at', '<', 2024)->delete();
	DB::table('clientes')->whereYear('created_at', '<', 2024)->delete();
	DB::table('proveedores')->whereYear('created_at', '<', 2024)->delete();

	return "Listo XD";
	
})->name('clearBD');

Route::get('/empresaAbonos', function(){
	
	$abonos = App\Models\Ventas\Abono::where('id_empresa', null)->get();
	
})->name('clearBD');

Route::get('/setDetalleCitas', function(){
	
	$eventos = App\Models\Eventos\Evento::all();

	foreach ($eventos as $evento) {
		$servicio = App\Models\Inventario\Producto::find($evento->id_servicio);

		if ($servicio) {
			$detalle = new App\Models\Eventos\Detalle;
			$detalle->cantidad = 1;
			$detalle->id_producto = $servicio->id;
			$detalle->id_evento = $evento->id;
			$detalle->save();
		}
	}
	return "Listo XD";
	
})->name('clearBD');

Route::get('/setPais', function(){
	
	$empresas = App\Models\Admin\Empresa::all();
	// $empresas = $empresas->groupBy('moneda');

	foreach ($empresas as $empresa) {
		if ($empresa->moneda == 'GTQ') {
			$empresa->pais = 'Guatemala';
		}
		if ($empresa->moneda == 'USD') {
			$empresa->pais = 'El Salvador';
		}
		$empresa->save();
	}

	return "Listo XD";
	
})->name('clearBD');

Route::get('/setIVA', function(){
	
	$empresas = App\Models\Admin\Empresa::where('cobra_iva', 'Si')
							->whereNotNull('iva')
							->with('impuestos')
							->doesntHave('impuestos')
							->get();

	foreach ($empresas as $empresa) {
		$impuesto = new \App\Models\Admin\Impuesto;
		$impuesto->nombre = 'IVA';
		$impuesto->porcentaje = $empresa->iva;
		$impuesto->id_empresa = $empresa->id;
		$impuesto->save();
	}

	return $empresas;
	
})->name('clearBD');


Auth::routes();

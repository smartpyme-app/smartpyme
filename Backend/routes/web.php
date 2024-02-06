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

Route::get('/setNotificaiones', function(){
	

	$fechaStart = \Carbon\Carbon::today();
	$fechaEnd = \Carbon\Carbon::today()->addDay(3);

	$gastos = App\Models\Compras\Gastos\Gasto::where('estado', 'Pendiente')
	                    ->whereBetween('fecha_pago', [$fechaStart, $fechaEnd])
	                    ->get();

	$compras = App\Models\Compras\Compra::where('estado', 'Pendiente')
	                    ->whereBetween('fecha_pago', [$fechaStart, $fechaEnd])
	                    ->get();

	
	$ventas = App\Models\Ventas\Venta::where('estado', 'Pendiente')
                            ->whereBetween('fecha_pago', [$fechaStart, $fechaEnd])
                            ->where('id_empresa', 190)
                            ->get();


	$productos = App\Models\Inventario\Producto::whereHas('inventarios', function ($query) {
                                $query->whereRaw('stock_minimo > 0')
                                        ->whereRaw('stock <= stock_minimo');
                            })
                            ->where('id_empresa', 190)
                            ->where('enable', true)->get();

	return $ventas;
	
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


Auth::routes();

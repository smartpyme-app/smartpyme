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


Route::get('/asignarAsesores', function(){

    $detalles = App\Models\Ventas\Detalle::where('id_empresa', 128)->get();


    foreach ($detalles as $detalle) {

        $paquete = Paquete::where('id_venta_detalle', $detalle->id)->first();
        if ($paquete) {
            $detalle->id_vendedor = $paquete->id_asesor;
            $detalle->save();

            $venta = $detalle->venta;
            $venta->id_vendedor = $paquete->id_asesor;
            $venta->save();
        }

    }

    return 'Listo';


});


Route::get('/',       			[HomeController::class, 'index'])->name('home');
Route::post('/demo',       		[HomeController::class, 'demoPost'])->name('demo');


Auth::routes();

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
use App\Http\Controllers\Auth\AuthJWTController;


// Route::get('/pago-wompi', [App\Http\Controllers\WompiController::class, 'pagoWompi'])->name('pagoWompi');

Route::get('/payment/{id}', [AuthJWTController::class, 'pagoCompletado'])->name('payment.n1co');
Route::get('/registro/{id}', [AuthJWTController::class, 'pagoFinish'])->name('payment.finish');

Route::get('/descargar-ticket/{id}', 	[AuthJWTController::class, 'suscription'])->name('suscripcion.ticket');


use App\Exports\TrasladosCombosExport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Maatwebsite\Excel\Facades\Excel;

Route::get('/traslados', function(){

    $tralados = new TrasladosCombosExport();
    // $tralados->filter($request);

    return Excel::download($tralados, 'tralados.xlsx');
});

use App\Exports\Inventario\InventarioAFechaExport;

Route::get('/inventariostock', function(){

    $inventario = new InventarioAFechaExport();
    return Excel::download($inventario, 'inventario.xlsx');

});


Route::get('/ventas-campos-nuevos', function () {
    $ventas = App\Models\Ventas\Venta::with('detalles', 'empresa')->get();

    foreach ($ventas as $venta) {
        if ($venta->iva > 0) {
          $venta->tipo_operacion = 'Gravada'; // Aplica IVA
        } else {
          $venta->tipo_operacion = 'No Gravada'; // No aplica IVA
        }

        // Tipo de renta
        if ($venta->detalles->count() > 0) {
            $detalle = $venta->detalles->first();

            if ($detalle->tipo == 'Servicio') {
                $venta->tipo_renta = $venta->empresa->tipo_renta_servicios ?? null;
            } else {
                $venta->tipo_renta = $venta->empresa->tipo_renta_productos ?? null;
            }
        }

        $venta->save();
    }

    return 'Ventas actualizadas correctamente.';
});

Route::get('/compras-campos-nuevos', function () {
    $compras = App\Models\Compras\Compra::with('empresa')->get();

    foreach ($compras as $compra) {
        if ($compra->iva > 0) {
          $compra->tipo_operacion = 'Gravada'; // Aplica IVA
        } else {
          $compra->tipo_operacion = 'No Gravada'; // No aplica IVA
        }

        $compra->tipo_clasificacion = 'Costo';
        $compra->tipo_costo_gasto = 'Costo artículos producidos/comprados interno';
        $compra->tipo_sector =  $compra->empresa->tipo_sector ?? null;
        $compra->save();

    }

    return 'Compras actualizadas correctamente.';
});

Route::get('/gastos-campos-nuevos', function () {
    $gastos = App\Models\Compras\Gastos\Gasto::with('empresa')->get();

    foreach ($gastos as $gasto) {
        if ($gasto->iva > 0) {
          $gasto->tipo_operacion = 'Gravada'; // Aplica IVA
        } else {
          $gasto->tipo_operacion = 'No Gravada'; // No aplica IVA
        }

        $gasto->tipo_clasificacion = 'Gasto';
        $gasto->tipo_costo_gasto = 'Gastos de venta sin donación';
        $gasto->tipo_sector =  $gasto->empresa->tipo_sector ?? null;
        $gasto->save();

    }

    return 'Gastos actualizadas correctamente.';
});

Route::get('/',       			[HomeController::class, 'index'])->name('home');
Route::post('/demo',       		[HomeController::class, 'demoPost'])->name('demo');


Auth::routes();

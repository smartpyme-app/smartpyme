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

Route::get('/ventassintipodte', function () {
    $ventas = App\Models\Ventas\Venta::whereNull('tipo_dte')
        ->whereNotNull('dte')
        ->whereYear('fecha', 2026)
        ->get();

    $ventasProcesadas = 0;

    foreach ($ventas as $venta) {
        $tipoDte = data_get($venta->dte, 'identificacion.tipoDte');

        if (!$tipoDte) {
            continue;
        }

        $venta->tipo_dte = $tipoDte;
        $venta->save();
        $ventasProcesadas++;
    }

    return "Se procesaron {$ventasProcesadas} ventas";
});


Route::get('/',       			[HomeController::class, 'index'])->name('home');
Route::post('/demo',       		[HomeController::class, 'demoPost'])->name('demo');

// Documentación API Externa (rutas públicas)
Route::get('/api/external/documentation', [App\Http\Controllers\Api\External\DocumentationController::class, 'index'])->name('external-api.documentation');
Route::get('/api/external/documentation/json', [App\Http\Controllers\Api\External\DocumentationController::class, 'json'])->name('external-api.json');

Auth::routes();

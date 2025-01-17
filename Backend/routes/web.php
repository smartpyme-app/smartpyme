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

Route::get('/pago-wompi', [App\Http\Controllers\WompiController::class, 'pagoWompi'])->name('pagoWompi');

Route::get('/payment/{id}', [AuthJWTController::class, 'pagoCompletado'])->name('payment.n1co');
Route::get('/registro/{id}', [AuthJWTController::class, 'pagoFinish'])->name('payment.finish');

Route::get('/descargar-ticket/{id}', 	[AuthJWTController::class, 'suscription'])->name('suscripcion.ticket');


use App\Exports\TrasladosCombosExport;
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

Route::get('/',       			[HomeController::class, 'index'])->name('home');
Route::post('/demo',       		[HomeController::class, 'demoPost'])->name('demo');


Auth::routes();

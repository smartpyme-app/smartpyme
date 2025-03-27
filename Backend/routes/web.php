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


Route::get('/',       			[HomeController::class, 'index'])->name('home');
Route::post('/demo',       		[HomeController::class, 'demoPost'])->name('demo');


Auth::routes();

<?php

use App\Http\Controllers\Api\Incentivos\VendedorIncentivosDashboardController;
use Illuminate\Support\Facades\Route;

Route::get('incentivos/vendedores', [VendedorIncentivosDashboardController::class, 'index']);
Route::get('incentivos/vendedores/{id_vendedor}', [VendedorIncentivosDashboardController::class, 'show']);

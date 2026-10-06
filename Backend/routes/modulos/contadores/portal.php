<?php

use App\Http\Controllers\Api\Contadores\ContadorPortalController;
use Illuminate\Support\Facades\Route;

Route::get('/contadores/empresas', [ContadorPortalController::class, 'empresas']);

<?php

use App\Http\Controllers\Api\Admin\AuditoriaController;
use Illuminate\Support\Facades\Route;

Route::get('auditoria', [AuditoriaController::class, 'index'])
    ->middleware('permission:auditoria.ver');

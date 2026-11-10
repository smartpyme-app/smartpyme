<?php

use App\Http\Controllers\Api\Admin\AuditoriaController;
use Illuminate\Support\Facades\Route;

Route::get('auditoria', [AuditoriaController::class, 'index'])
    ->middleware('role_or_permission:'.config('constants.ROL_ADMIN', 'admin').'|auditoria.ver');

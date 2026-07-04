<?php

use App\Http\Controllers\Api\SuperAdmin\AuditoriaController;
use Illuminate\Support\Facades\Route;

Route::get('auditoria', [AuditoriaController::class, 'index'])
    ->middleware(['superadmin', 'permission:auditoria.plataforma.ver']);

<?php

use App\Http\Controllers\Api\SuperAdmin\AuditoriaController;
use Illuminate\Support\Facades\Route;

Route::get('admin-auditoria', [AuditoriaController::class, 'index'])
    ->middleware(['superadmin', 'permission:auditoria.plataforma.ver']);

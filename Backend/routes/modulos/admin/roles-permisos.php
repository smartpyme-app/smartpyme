<?php

use App\Http\Controllers\Api\Admin\RolePermissionController;
use Illuminate\Support\Facades\Route;
Route::prefix('roles-permissions')->group(function () {
    Route::get('/', [RolePermissionController::class, 'index']);
    Route::post('/assign-role', [RolePermissionController::class, 'assignRoleToUser']);
    Route::post('/remove-role', [RolePermissionController::class, 'removeRoleFromUser']);
    Route::post('/assign-permission-to-role', [RolePermissionController::class, 'assignPermissionToRole']);
    Route::post('/remove-permission-from-role', [RolePermissionController::class, 'removePermissionFromRole']);
    Route::post('/assign-permission-to-user', [RolePermissionController::class, 'assignPermissionToUser']);
    Route::post('/remove-permission-from-user', [RolePermissionController::class, 'removePermissionFromUser']);
});

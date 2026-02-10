<?php 

use App\Http\Controllers\Api\Admin\DashboardsController;

    Route::get('/dashboards',                 [DashboardsController::class, 'index']);
    Route::get('/dashboards/list',                 [DashboardsController::class, 'list']);
    Route::post('/dashboard',                 [DashboardsController::class, 'store']);
    Route::get('/dashboard/{id}',             [DashboardsController::class, 'read']);
    Route::delete('/dashboard/{id}',             [DashboardsController::class, 'delete']);




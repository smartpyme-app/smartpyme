<?php

use App\Http\Controllers\Api\SuperAdmin\HomeBannersController;

Route::middleware('role:super_admin')->group(function () {
    Route::get('/home-banners/admin', [HomeBannersController::class, 'index']);
    Route::post('/home-banner', [HomeBannersController::class, 'store']);
    Route::delete('/home-banner/{id}', [HomeBannersController::class, 'delete']);
});
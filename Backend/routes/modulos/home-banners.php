<?php

use App\Http\Controllers\Api\HomeBannersController;

Route::get('/home-banners', [HomeBannersController::class, 'index']);
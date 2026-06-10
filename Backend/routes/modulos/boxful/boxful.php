<?php

use App\Http\Controllers\BoxFull\BoxFullController;
use Illuminate\Support\Facades\Route;

Route::prefix('boxful')->group(function () {
    Route::get('test-connection', [BoxFullController::class, 'testConnection']);
});

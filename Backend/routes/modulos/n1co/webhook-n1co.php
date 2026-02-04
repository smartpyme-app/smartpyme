<?php

use App\Http\Controllers\Auth\AuthJWTController;
use App\Http\Controllers\WebhookN1coController;
use Illuminate\Support\Facades\Route;

Route::post('/n1co/webhook', [WebhookN1coController::class, 'handle']);
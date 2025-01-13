<?php

use App\Http\Controllers\N1coWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/n1co/webhook', [N1coWebhookController::class, 'handle']);

?>

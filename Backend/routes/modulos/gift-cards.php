<?php

use App\Http\Controllers\Api\GiftCards\GiftCardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['verificar.funcionalidad:gift-cards'])->group(function () {
    Route::get('gift-cards/by-codigo/{codigo}', [GiftCardController::class, 'byCodigo']);
    Route::get('gift-cards', [GiftCardController::class, 'index']);
});

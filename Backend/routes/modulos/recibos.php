<?php

    use App\Http\Controllers\Api\RecibosController;
    
    // Recibos
        Route::post('/recibo/crear', [RecibosController::class, 'store'])->name('recibo.save');
        Route::get('/recibo/pdf/{id}', [RecibosController::class, 'print'])->name('recibo.print');

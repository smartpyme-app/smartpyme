<?php

use App\Http\Controllers\Api\Admin\CustomConfigController;
use Illuminate\Support\Facades\Route;


// Route::prefix('admin/custom-config')->middleware(['auth'])->group(function () {
//     // Obtener configuración
//     Route::get('/', [CustomConfigController::class, 'getConfig'])->name('custom-config.get');
    
//     // Actualizar columnas
//     Route::put('/columns', [CustomConfigController::class, 'updateColumns'])->name('custom-config.update-columns');
    
//     // Actualizar configuración específica
//     Route::put('/config', [CustomConfigController::class, 'updateConfig'])->name('custom-config.update');
    
//     // Agregar nueva sección
//     Route::post('/section', [CustomConfigController::class, 'addConfigSection'])->name('custom-config.add-section');
    
//     // Restablecer configuración
//     Route::post('/reset', [CustomConfigController::class, 'resetConfig'])->name('custom-config.reset');
// });
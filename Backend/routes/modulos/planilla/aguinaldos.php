<?php

use App\Http\Controllers\Api\Planilla\AguinaldosController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'aguinaldos', 'middleware' => ['auth:api']], function () {
    Route::controller(AguinaldosController::class)->group(function () {
        // Listar aguinaldos
        Route::get('/', 'index');
        
        // Crear aguinaldo vacío
        Route::post('/', 'store');
        
        // Ver detalle de aguinaldo
        Route::get('/{id}', 'show');
        
        // Actualizar fecha de cálculo
        Route::put('/{id}/fecha-calculo', 'actualizarFechaCalculo');
        
        // Agregar empleado al aguinaldo
        Route::post('/{id}/agregar-empleado', 'agregarEmpleado');
        
        // Procesar pago del aguinaldo
        Route::post('/{id}/pagar', 'processPayment');
        
        // Exportar aguinaldo
        Route::get('/{id}/excel', 'exportExcel');
        Route::get('/{id}/pdf', 'exportPDF');
        
        // Eliminar aguinaldo
        Route::delete('/{id}', 'destroy');
        
        // Obtener sugerencia de aguinaldo
        Route::post('/sugerencia', 'obtenerSugerenciaAguinaldo');
        
        // Calcular preview de aguinaldo
        Route::post('/preview', 'calcularPreview');
    });
});

// Rutas para detalles de aguinaldo
Route::group(['prefix' => 'aguinaldo-detalles', 'middleware' => ['auth:api']], function () {
    Route::controller(AguinaldosController::class)->group(function () {
        // Actualizar detalle (monto y recalcular)
        Route::put('/{id}', 'actualizarEmpleado');
        
        // Eliminar empleado del aguinaldo
        Route::delete('/{id}', 'eliminarEmpleado');
    });
});

<?php

use App\Http\Controllers\ClienteNotasController;
use Illuminate\Support\Facades\Route;

// Rutas para gestión de notas y visitas de clientes
Route::prefix('cliente-notas')->group(function () {
    
    // Obtener notas de un cliente
    Route::get('notas/{clienteId}', [ClienteNotasController::class, 'getNotas']);
    
    // Obtener visitas de un cliente
    Route::get('visitas/{clienteId}', [ClienteNotasController::class, 'getVisitas']);
    
    // Obtener estadísticas de notas y visitas
    Route::get('estadisticas/{clienteId}', [ClienteNotasController::class, 'getEstadisticas']);
    
    // Buscar notas por contenido
    Route::get('buscar/{clienteId}', [ClienteNotasController::class, 'buscarNotas']);
    
    // Crear nueva nota
    Route::post('notas', [ClienteNotasController::class, 'crearNota']);
    
    // Crear nueva visita
    Route::post('visitas', [ClienteNotasController::class, 'crearVisita']);
    
    // Actualizar nota
    Route::put('notas/{notaId}', [ClienteNotasController::class, 'actualizarNota']);
    
    // Actualizar visita
    Route::put('visitas/{visitaId}', [ClienteNotasController::class, 'actualizarVisita']);
    
    // Eliminar nota
    Route::delete('notas/{notaId}', [ClienteNotasController::class, 'eliminarNota']);
    
    // Eliminar visita
    Route::delete('visitas/{visitaId}', [ClienteNotasController::class, 'eliminarVisita']);
});

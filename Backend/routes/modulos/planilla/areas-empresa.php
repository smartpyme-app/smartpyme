<?php 

use App\Http\Controllers\Api\Planilla\AreasEmpresaController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'area-empresa', 'middleware' => ['auth:api']], function () {
    Route::controller(AreasEmpresaController::class)->group(function () {
        Route::get('/list', 'list');                       // Lista simple para selectores
        Route::get('/list_departamentos', 'list_departamentos');                       // Lista simple para selectores
        Route::get('/exportar', 'exportar');               // Exportar a Excel
        Route::post('/cambiar-estado-multiple', 'cambiarEstadoMultiple'); // Cambiar estado en lote
        Route::get('/', 'index');                          // Listar con filtros y paginación
        Route::post('/', 'store');                         // Crear o actualizar
        Route::get('/{id}', 'show');                       // Mostrar área específica
        Route::delete('/{id}', 'destroy');                 // Eliminar área
    });
});
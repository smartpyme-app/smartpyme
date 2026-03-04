<?php 

use App\Http\Controllers\Api\Planilla\EmpleadosController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'empleados', 'middleware' => ['auth:api']], function () {
    Route::controller(EmpleadosController::class)->group(function () {
        Route::get('/', 'index');
        Route::get('/list', 'list');
        Route::get('/{id}', 'show');
        Route::post('/', 'store');
        Route::put('/{id}', 'update');
        Route::delete('/{id}', 'destroy');
        Route::post('/cambiar-estado/{id}', 'cambiarEstado');
        Route::post('/{id}/dar-baja', 'darBaja');
        Route::post('/{id}/dar-alta', 'darAlta');
        Route::get('/{id}/historialesContratos', 'getHistorialesContratos');
        Route::get('/{id}/historialesBajas', 'getHistorialesBajas');
        Route::get('documentos/{id}/descargar', 'descargarDocumento');
        Route::get('contratos/{id}/descargar', 'descargarContrato');
        Route::post('{id}/documentos', 'subirDocumentos');
        Route::get('{id}/documentos', 'getDocumentos');
        Route::post('/importar', 'importar');


    });
});
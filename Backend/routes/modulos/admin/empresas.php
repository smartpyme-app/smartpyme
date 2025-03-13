<?php 

use App\Http\Controllers\Api\Admin\EmpresasController;
use App\Http\Controllers\Api\Admin\DashboardsController;
use App\Http\Controllers\Api\Admin\ReportesController;
//use Route;
use Illuminate\Support\Facades\Route;

    Route::get('/empresas',        	        [EmpresasController::class, 'index'])->middleware('superadmin');
    Route::get('/empresas/list',            [EmpresasController::class, 'list'])->middleware('superadmin');
    Route::post('/empresa',                 [EmpresasController::class, 'store']);
    Route::get('/empresa/{id}',             [EmpresasController::class, 'read']);

    Route::post('/empresa/eliminar/datos',   [EmpresasController::class, 'eliminarDatos'])->middleware('admin');

    Route::get('/suscripcion',             [EmpresasController::class, 'suscripcion']);
    Route::get('/suscripcion/recibo/pdf/{id}',             [EmpresasController::class, 'printRecibo']);

    Route::get('/dashboards',                 [DashboardsController::class, 'index']);
    Route::get('/dashboards/list',                 [DashboardsController::class, 'list']);
    Route::post('/dashboard',                 [DashboardsController::class, 'store']);
    Route::get('/dashboard/{id}',             [DashboardsController::class, 'read']);
    Route::delete('/dashboard/{id}',             [DashboardsController::class, 'delete']);
    
    Route::post('/reporte/requisicion-compras',    [ReportesController::class, 'requisicionCompra']);
    Route::get('/reporte/corte/{id}',              [ReportesController::class, 'corte']);


?>

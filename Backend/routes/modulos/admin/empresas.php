<?php 

use App\Http\Controllers\Api\Admin\EmpresasController;
use App\Http\Controllers\Api\Admin\DashboardsController;
use App\Http\Controllers\Api\Admin\ReportesController;
use Illuminate\Support\Facades\Route;

    Route::get('/empresas',        	        [EmpresasController::class, 'index'])->middleware('role:super_admin');
    Route::get('/empresas/list',            [EmpresasController::class, 'list'])->middleware('role:super_admin');

    Route::get('/empresa/get-alert',     [EmpresasController::class, 'getAlertSuscription']);
    Route::get('/empresa/isvisible-alert', [EmpresasController::class, 'isVisibleAlertSuscription']);

    Route::post('/empresa',                 [EmpresasController::class, 'store']);
    Route::get('/empresa/{id}',             [EmpresasController::class, 'read']);

    Route::post('/empresa/eliminar/datos',   [EmpresasController::class, 'eliminarDatos'])->middleware('role:admin');

    Route::get('/suscripcion',             [EmpresasController::class, 'suscripcion']);
    Route::get('/suscripcion/recibo/pdf/{id}',             [EmpresasController::class, 'printRecibo']);

    Route::post('/empresa/imagenes',             [EmpresasController::class, 'storeImagenes']);
    Route::post('/empresa/fe-cr-certificado',         [EmpresasController::class, 'uploadFeCrCertificado']);
    Route::get('/empresa/fe-cr-certificado-estado',   [EmpresasController::class, 'estadoFeCrCertificado']);
    Route::post('/empresa/fe-cr-probar-conexion',     [EmpresasController::class, 'probarConexionFeCr']);
    Route::get('/dashboards',                 [DashboardsController::class, 'index']);
    Route::get('/dashboards/list',                 [DashboardsController::class, 'list']);
    Route::post('/dashboard',                 [DashboardsController::class, 'store']);
    Route::get('/dashboard/{id}',             [DashboardsController::class, 'read']);
    Route::delete('/dashboard/{id}',             [DashboardsController::class, 'delete']);
    
    Route::post('/reporte/requisicion-compras',    [ReportesController::class, 'requisicionCompra']);
    Route::get('/reporte/corte/{id}',              [ReportesController::class, 'corte']);





?>

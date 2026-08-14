<?php
use App\Http\Controllers\Api\Admin\ReporteConfiguracionController;
use Illuminate\Support\Facades\Route;

Route::get('reportes-configuracion', [ReporteConfiguracionController::class, 'index']);
Route::post('reportes-configuracion', [ReporteConfiguracionController::class, 'store']);

Route::post('reportes-configuracion/enviar-prueba', [ReporteConfiguracionController::class, 'enviarPrueba']);
Route::post('reportes-configuracion/exportar', [ReporteConfiguracionController::class, 'exportar']);
Route::post('reportes-configuracion/exportar-pdf', [ReporteConfiguracionController::class, 'exportarPDF']);

Route::get('reportes-configuracion/exportaciones/{id}', [ReporteConfiguracionController::class, 'estadoExportacion']);
Route::get('reportes-configuracion/exportaciones/{id}/archivo', [ReporteConfiguracionController::class, 'descargarExportacion']);

Route::put('reportes-configuracion/estado/{id}', [ReporteConfiguracionController::class, 'updateEstado']);
Route::get('reportes-configuracion/{id}', [ReporteConfiguracionController::class, 'show']);
Route::delete('reportes-configuracion/{id}', [ReporteConfiguracionController::class, 'destroy']);

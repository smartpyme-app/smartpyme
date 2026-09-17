<?php



use App\Http\Controllers\Api\Contabilidad\Activos\ActivosConfiguracionController;

use App\Http\Controllers\Api\Contabilidad\Activos\ActivosController;

use App\Http\Controllers\Api\Contabilidad\Activos\ActivosReportesController;

use App\Http\Controllers\Api\Contabilidad\Activos\CategoriasController;

use App\Http\Controllers\Api\Contabilidad\Activos\DepreciacionController;



Route::middleware(['verificar.funcionalidad:contabilidad'])->group(function () {

    Route::get('/activos/reportes/{tipo}',                  [ActivosReportesController::class, 'reporte']);

    Route::get('/activos/reportes/{tipo}/descargar/{formato}', [ActivosReportesController::class, 'descargar']);



    Route::get('/activos',             [ActivosController::class, 'index']);

    Route::get('/activos/prefill-egreso/{id}',     [ActivosController::class, 'prefillFromEgreso']);

    Route::get('/activos/prefill-compra-detalle/{id}', [ActivosController::class, 'prefillFromCompraDetalle']);

    Route::get('/activos/pendientes-compra/{id}',  [ActivosController::class, 'pendientesCompra']);

    Route::post('/activo',             [ActivosController::class, 'store']);

    Route::post('/activo/{id}/baja',   [ActivosController::class, 'baja']);

    Route::get('/activo/{id}/depreciaciones',        [DepreciacionController::class, 'cronograma']);

    Route::get('/activo/{id}',         [ActivosController::class, 'read']);

    Route::post('/activos/filtrar',    [ActivosController::class, 'filter']);

    Route::delete('/activo/{id}',      [ActivosController::class, 'delete']);



    Route::get('/activos/categorias',             [CategoriasController::class, 'index']);

    Route::post('/activos/categorias/importar-plantillas', [CategoriasController::class, 'importarPlantillas']);

    Route::post('/activos/categoria',             [CategoriasController::class, 'store']);

    Route::delete('/activos/categoria/{id}',         [CategoriasController::class, 'delete']);



    Route::get('/activos/configuracion',            [ActivosConfiguracionController::class, 'show']);

    Route::post('/activos/configuracion',           [ActivosConfiguracionController::class, 'store']);



    Route::get('/activos/depreciacion/preview',      [DepreciacionController::class, 'preview']);

    Route::post('/activos/depreciacion/ejecutar',   [DepreciacionController::class, 'ejecutar']);

});



?>


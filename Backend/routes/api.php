<?php


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

use App\Http\Controllers\Api\Admin\EmpresasController;
use App\Http\Controllers\Api\Admin\EmpresasFuncionalidadesController;
use App\Http\Controllers\Api\Admin\SuscripcionesController;
use App\Http\Controllers\Api\Constants\ConstantsController;
use App\Http\Controllers\n1co\EstadoController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\n1co\N1coChargeController;

Route::get('/prueba', function () {
	return Response()->json(['message' => 'Success'], 200);
});

Route::get('/prueba-nova', function () {
	\Illuminate\Support\Facades\Log::info('prueba-nova route visited at: ' . now()->toDateTimeString());
	
	\App\Jobs\TestNovaJob::dispatch('Test message from prueba-nova route at ' . now()->toDateTimeString())->onQueue('smartpyme-main');
	
	return Response()->json([
		'message' => 'NOVA test route visited and job dispatched',
		'timestamp' => now()->toDateTimeString(),
		'queue' => 'smartpyme-main'
	], 200);
});

Route::get('verificar-acceso/{slug}', [EmpresasFuncionalidadesController::class, 'verificarAcceso']);

// EventBridge Cron Endpoints
Route::prefix('cron')->middleware(\App\Http\Middleware\CronApiKeyMiddleware::class)->group(function () {
	Route::post('/generate-notificaciones', function () {
		\Illuminate\Support\Facades\Log::info('EventBridge triggered: generate:notificaciones at ' . now());
		
		\Illuminate\Support\Facades\Artisan::call('generate:notificaciones');
		
		return response()->json([
			'status' => 'success',
			'command' => 'generate:notificaciones',
			'timestamp' => now(),
			'output' => \Illuminate\Support\Facades\Artisan::output()
		]);
	});
	
	Route::post('/test', function () {
		\Illuminate\Support\Facades\Log::info('EventBridge test endpoint called at ' . now());
		
		$response = [
			'status' => 'success',
			'message' => 'API key authentication working correctly',
			'timestamp' => now(),
			'environment' => app()->environment()
		];
		
		\Illuminate\Support\Facades\Log::info('EventBridge test response: ' . json_encode($response));
		
		return response()->json($response);
	});
	
	Route::post('/suscripciones-verificar', function () {
		\Illuminate\Support\Facades\Log::info('EventBridge triggered: suscripciones:verificar at ' . now());
		
		\Illuminate\Support\Facades\Artisan::call('suscripciones:verificar');
		
		return response()->json([
			'status' => 'success',
			'command' => 'suscripciones:verificar',
			'timestamp' => now(),
			'output' => \Illuminate\Support\Facades\Artisan::output()
		]);
	});
	
	Route::post('/suscripciones-procesar-cargos', function () {
		\Illuminate\Support\Facades\Log::info('EventBridge triggered: suscripciones:procesar-cargos at ' . now());
		
		\Illuminate\Support\Facades\Artisan::call('suscripciones:procesar-cargos');
		
		return response()->json([
			'status' => 'success',
			'command' => 'suscripciones:procesar-cargos',
			'timestamp' => now(),
			'output' => \Illuminate\Support\Facades\Artisan::output()
		]);
	});
	
	Route::post('/kardex-procesar-cola', function () {
		\Illuminate\Support\Facades\Log::info('EventBridge triggered: kardex:procesar-cola at ' . now());
		
		\Illuminate\Support\Facades\Artisan::call('kardex:procesar-cola');
		
		return response()->json([
			'status' => 'success',
			'command' => 'kardex:procesar-cola',
			'timestamp' => now(),
			'output' => \Illuminate\Support\Facades\Artisan::output()
		]);
	});
	
	Route::post('/trabajos-procesar-shopify', function () {
		\Illuminate\Support\Facades\Log::info('EventBridge triggered: trabajos:procesar --solo-imagenes-shopify --limite=1000 at ' . now());
		
		\Illuminate\Support\Facades\Artisan::call('trabajos:procesar', [
			'--solo-imagenes-shopify' => true,
			'--limite' => 1000
		]);
		
		return response()->json([
			'status' => 'success',
			'command' => 'trabajos:procesar --solo-imagenes-shopify --limite=1000',
			'timestamp' => now(),
			'output' => \Illuminate\Support\Facades\Artisan::output()
		]);
	});
});


// N1co
require base_path('routes/modulos/n1co/webhook-n1co.php');
require base_path('routes/modulos/n1co/suscripciones-n1co.php');

// require base_path('routes/modulos/n1co/payment.php');
require base_path('routes/modulos/auth.php');

Route::group(['middleware' => ['jwt.auth']], function () {

	Route::get('constants', [ConstantsController::class, 'getAppConstants']);

	Route::get('/suscripcion/get-alert',     [SuscripcionesController::class, 'getAlertSuscription']);
	Route::get('/suscripcion/isvisible-alert', [SuscripcionesController::class, 'isVisibleAlertSuscription']);

	require base_path('routes/modulos/dash.php');
	require base_path('routes/modulos/facturacion.php');
	require base_path('routes/modulos/recibos.php');

	// Ventas
	require base_path('routes/modulos/ventas/ventas.php');
	require base_path('routes/modulos/ventas/detalles.php');
	require base_path('routes/modulos/ventas/devoluciones.php');
	require base_path('routes/modulos/ventas/cotizaciones.php');
	require base_path('routes/modulos/ventas/abonos.php');
	require base_path('routes/modulos/ventas/clientes.php');

	// Compras
	require base_path('routes/modulos/compras/compras.php');
	require base_path('routes/modulos/compras/detalles.php');
	require base_path('routes/modulos/compras/devoluciones.php');
	require base_path('routes/modulos/compras/gastos.php');
	require base_path('routes/modulos/compras/gastos-abonos.php');
	require base_path('routes/modulos/compras/proveedores.php');
	require base_path('routes/modulos/compras/abonos.php');
	require base_path('routes/modulos/compras/ordenes-de-compras.php');

	// Inventario
	require base_path('routes/modulos/inventario/productos.php');
	require base_path('routes/modulos/inventario/servicios.php');
	require base_path('routes/modulos/inventario/materias-primas.php');
	require base_path('routes/modulos/inventario/inventarios.php');
	require base_path('routes/modulos/inventario/categorias.php');
	require base_path('routes/modulos/inventario/traslados.php');
	require base_path('routes/modulos/inventario/ajustes.php');
	require base_path('routes/modulos/inventario/bodegas.php');
	require base_path('routes/modulos/inventario/paquetes.php');
	require base_path('routes/modulos/inventario/entradas-salidas.php');

	// Eventos
	require base_path('routes/modulos/eventos/eventos.php');

	// Empleados
	// require base_path('routes/modulos/empleados/empleados.php');
	// require base_path('routes/modulos/empleados/planillas.php');
	// require base_path('routes/modulos/empleados/comisiones.php');
	// require base_path('routes/modulos/empleados/asistencias.php');
	// require base_path('routes/modulos/empleados/metas.php');

	// Contabilidad
	require base_path('routes/modulos/contabilidad/activos.php');
	require base_path('routes/modulos/contabilidad/cajas-chicas.php');
	require base_path('routes/modulos/contabilidad/presupuestos.php');
	require base_path('routes/modulos/contabilidad/proyectos.php');
	require base_path('routes/modulos/contabilidad/libros-iva.php');

	// Admin
	require base_path('routes/modulos/admin/empresas.php');
	require base_path('routes/modulos/admin/sucursales.php');
	require base_path('routes/modulos/admin/dashboards.php');
	require base_path('routes/modulos/admin/cajas.php');
	require base_path('routes/modulos/admin/impuestos.php');
	require base_path('routes/modulos/admin/formasdepago.php');
	require base_path('routes/modulos/admin/canales.php');
	require base_path('routes/modulos/admin/notificaciones.php');
	require base_path('routes/modulos/admin/bancos.php');
	require base_path('routes/modulos/admin/usuarios.php');
	require base_path('routes/modulos/admin/accesos.php');
	require base_path('routes/modulos/admin/licencias.php');
	require base_path('routes/modulos/admin/suscripciones.php');
	require base_path('routes/modulos/admin/MH.php');
    require base_path('routes/modulos/admin/reportes-automaticos.php');


    // Super Admin
	require base_path('routes/modulos/super-admin/usuarios.php');
	require base_path('routes/modulos/super-admin/planes.php');
	require base_path('routes/modulos/super-admin/pagos.php');
	require base_path('routes/modulos/super-admin/transacciones.php');

	//Crequire base_path('rhatbot
	require base_path('routes/modulos/chat/chat.php');
	//Funcionalidades
	require base_path('routes/modulos/funcionalidades/funcionalidades.php');

	// Pruebas masivas
	require base_path('routes/modulos/admin/pruebas-masivas-mh.php');

	// planillas

	require base_path('routes/modulos/planilla/empleados.php');
	require base_path('routes/modulos/planilla/planillas.php');
	require base_path('routes/modulos/planilla/configuraciones.php');
	require base_path('routes/modulos/planilla/cargos.php');
	require base_path('routes/modulos/planilla/departamentos-planilla.php');

	require base_path('routes/modulos/planilla/historialcontratos.php');

});

//webhook

require base_path('routes/modulos/webhook/webhook.php');

// Route::get('/api/pago-completado/{id}', [AuthJWTController::class, 'pagoCompletado'])->name('pagoCompletado');

//whatsapp
require base_path('routes/modulos/whatsapp/whatsapp.php');

Route::get('/prueba/factura', function () {
	return view('reportes/pruebas/factura');
});

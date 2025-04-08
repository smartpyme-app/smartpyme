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
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Constants\ConstantsController;
use App\Http\Controllers\n1co\N1coChargeController;

Route::get('/prueba', function () {
	return Response()->json(['message' => 'Success'], 200);
});

Route::get('verificar-acceso/{slug}', [EmpresasFuncionalidadesController::class, 'verificarAcceso']);


// N1co
require base_path('routes/modulos/n1co/webhook-n1co.php');

Route::group(['prefix' => 'payment'], function () {
	Route::post('method', [N1coChargeController::class, 'createPaymentMethod']);
	Route::post('process', [N1coChargeController::class, 'processCharge']);
	Route::post('process-ready', [N1coChargeController::class, 'processChargeReady']);
	Route::post('process/3ds', [N1coChargeController::class, 'processCharge3DS']);
	Route::post('update-method-payment', [N1coChargeController::class, 'updateMethodPayment']);
	Route::post('check-auth-status', [N1coChargeController::class, 'checkAuthenticationStatus']);
	Route::get('validate/{paymentId}', [N1coChargeController::class, 'validatePayment']);
	Route::get('{empresaId}', [N1coChargeController::class, 'checkout']);

});

// require base_path('routes/modulos/n1co/payment.php');
require base_path('routes/modulos/auth.php');

Route::group(['middleware' => ['jwt.auth']], function () {

	Route::get('constants', [ConstantsController::class, 'getAppConstants']);

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

	// Eventos
	require base_path('routes/modulos/eventos/eventos.php');

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

	// Pruebas masivas
	require base_path('routes/modulos/admin/pruebas-masivas-mh.php');

	// planillas

	require base_path('routes/modulos/planilla/empleados.php');
	require base_path('routes/modulos/planilla/planillas.php');
	require base_path('routes/modulos/planilla/cargos.php');
	require base_path('routes/modulos/planilla/departamentos-planilla.php');
    require base_path('routes/modulos/planilla/historialcontratos.php');

	//Chatbot
	require base_path('routes/modulos/chat/chat.php');

	//Funcionalidades
	require base_path('routes/modulos/funcionalidades/funcionalidades.php');

});

// Webhook
require base_path('routes/modulos/webhook/webhook.php');



// Route::get('/api/pago-completado/{id}', [AuthJWTController::class, 'pagoCompletado'])->name('pagoCompletado');



Route::get('/prueba/factura', function () {
	return view('reportes/pruebas/factura');
});

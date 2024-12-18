<?php

use Illuminate\Support\Facades\Route;

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

Route::get('/prueba', function(){ return Response()->json(['message' => 'Success'], 200); });

require base_path('routes/modulos/auth.php');

Route::group(['middleware' => ['jwt.auth']], function () {

//
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
		require base_path('routes/modulos/inventario/custom-fields.php');

	// Eventos
		require base_path('routes/modulos/eventos/eventos.php');

	// Empleados
		require base_path('routes/modulos/empleados/empleados.php');
		require base_path('routes/modulos/empleados/planillas.php');
		require base_path('routes/modulos/empleados/comisiones.php');
		require base_path('routes/modulos/empleados/asistencias.php');
        require base_path('routes/modulos/empleados/metas.php');

	// Contabilidad
		require base_path('routes/modulos/contabilidad/api.php');
		require base_path('routes/modulos/contabilidad/presupuestos.php');
		require base_path('routes/modulos/contabilidad/proyectos.php');
		require base_path('routes/modulos/contabilidad/catalogo.php');
		require base_path('routes/modulos/contabilidad/configuracion.php');
		require base_path('routes/modulos/contabilidad/partidas.php');
		require base_path('routes/modulos/contabilidad/reportes.php');

	// Bancos
		require base_path('routes/modulos/bancos/cuentas.php');
		require base_path('routes/modulos/bancos/cheques.php');
		require base_path('routes/modulos/bancos/transacciones.php');
		require base_path('routes/modulos/bancos/conciliaciones.php');

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
		require base_path('routes/modulos/admin/MH.php');

	// Super Admin
		require base_path('routes/modulos/super-admin/usuarios.php');
		require base_path('routes/modulos/super-admin/transacciones.php');

	// Ordenes de producción
		require base_path('routes/modulos/ventas/orden_produccion.php');


});


Route::get('/prueba/factura', function () {
	return view('reportes/pruebas/factura');
});

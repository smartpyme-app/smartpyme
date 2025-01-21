<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    public function run()
    {
        // Definir todos los roles primero
        $roles = [
            'ROL_SUPER_ADMIN' => 'super_admin',
            'ROL_ADMIN' => 'admin',
            'ROL_CONTADOR_SUPERIOR' => 'contador_superior',
            'ROL_CONTADOR_AUXILIAR' => 'contador_auxiliar',
            'ROL_USUARIO_SUPERVISOR' => 'usuario_supervisor',
            'ROL_GERENTE_OPERACIONES' => 'gerente_operaciones',
            'ROL_GERENTE_COMPRAS' => 'gerente_compras',
            'ROL_USUARIO' => 'usuario',
            'ROL_USUARIO_VENTAS' => 'usuario_ventas',
            'ROL_USUARIO_CITAS' => 'usuario_citas',
            'ROL_USUARIO_CONSULTAS' => 'usuario_consultas',
            'ROL_USUARIO_CAJERO' => 'usuario_cajero',
            'ROL_USUARIO_VENDEDOR' => 'usuario_vendedor',
            'ROL_USUARIO_COCINERO' => 'usuario_cocinero'
        ];

        // Crear todos los roles
        foreach ($roles as $configKey => $roleName) {
            Role::updateOrCreate(['name' => config("constants.{$configKey}", $roleName)]);
        }

        // Super Admin - Acceso Total
        $superAdmin = Role::findByName(config('constants.ROL_SUPER_ADMIN', 'super_admin'));
        $superAdmin->givePermissionTo(Permission::all());

        // Admin - Acceso Total
        $admin = Role::findByName(config('constants.ROL_ADMIN', 'admin'));
        $admin->givePermissionTo(Permission::all());

        // Contador Superior
        $contadorSuperior = Role::findByName(config('constants.ROL_CONTADOR_SUPERIOR', 'contador_superior'));
        $contadorSuperior->givePermissionTo([
            // Ventas - Solo Ver
            config('permissions.PERMISSION_VENTAS.ver'),
            config('permissions.PERMISSION_VENTAS.registros.ver'),
            config('permissions.PERMISSION_VENTAS.cotizaciones.ver'),
            // Compras
            config('permissions.PERMISSION_COMPRAS.ver'),
            config('permissions.PERMISSION_COMPRAS.registros.ver'),
            config('permissions.PERMISSION_COMPRAS.ordenes_compra.ver'),
            config('permissions.PERMISSION_COMPRAS.proveedores.ver'),
            // Gastos
            config('permissions.PERMISSION_GASTOS.ver'),
            config('permissions.PERMISSION_GASTOS.registros.ver'),
            config('permissions.PERMISSION_GASTOS.categorias.ver'),
            // Contabilidad - Acceso Total
            config('permissions.PERMISSION_CONTABILIDAD.ver'),
            config('permissions.PERMISSION_CONTABILIDAD.crear'),
            config('permissions.PERMISSION_CONTABILIDAD.editar'),
            config('permissions.PERMISSION_CONTABILIDAD.eliminar'),
            config('permissions.PERMISSION_CONTABILIDAD.partidas.ver'),
            config('permissions.PERMISSION_CONTABILIDAD.partidas.crear'),
            config('permissions.PERMISSION_CONTABILIDAD.partidas.editar'),
            config('permissions.PERMISSION_CONTABILIDAD.partidas.eliminar'),
            config('permissions.PERMISSION_CONTABILIDAD.catalogo_cuentas.ver'),
            config('permissions.PERMISSION_CONTABILIDAD.catalogo_cuentas.crear'),
            config('permissions.PERMISSION_CONTABILIDAD.catalogo_cuentas.editar'),
            config('permissions.PERMISSION_CONTABILIDAD.catalogo_cuentas.eliminar'),
            config('permissions.PERMISSION_CONTABILIDAD.configuracion.ver'),
            config('permissions.PERMISSION_CONTABILIDAD.configuracion.crear'),
            config('permissions.PERMISSION_CONTABILIDAD.configuracion.editar'),
            config('permissions.PERMISSION_CONTABILIDAD.configuracion.eliminar'),
            // Finanzas
            config('permissions.PERMISSION_FINANZAS.ver'),
            config('permissions.PERMISSION_FINANZAS.crear'),
            config('permissions.PERMISSION_FINANZAS.editar'),
            config('permissions.PERMISSION_FINANZAS.eliminar'),
            config('permissions.PERMISSION_FINANZAS.bancos.ver'),
            config('permissions.PERMISSION_FINANZAS.bancos.crear'),
            config('permissions.PERMISSION_FINANZAS.bancos.editar'),
            config('permissions.PERMISSION_FINANZAS.bancos.eliminar'),
            config('permissions.PERMISSION_FINANZAS.libro_iva.ver'),
            config('permissions.PERMISSION_FINANZAS.libro_iva.crear'),
            config('permissions.PERMISSION_FINANZAS.libro_iva.editar'),
            config('permissions.PERMISSION_FINANZAS.libro_iva.eliminar'),
            config('permissions.PERMISSION_FINANZAS.reporteria.ver')
        ]);

        // Contador Auxiliar
        $contadorAuxiliar = Role::findByName(config('constants.ROL_CONTADOR_AUXILIAR', 'contador_auxiliar'));
        $contadorAuxiliar->givePermissionTo([
            config('permissions.PERMISSION_VENTAS.ver'),
            config('permissions.PERMISSION_COMPRAS.ver'),
            config('permissions.PERMISSION_GASTOS.ver'),
            config('permissions.PERMISSION_CONTABILIDAD.ver'),
            config('permissions.PERMISSION_CONTABILIDAD.partidas.ver'),
            config('permissions.PERMISSION_CONTABILIDAD.catalogo_cuentas.ver'),
            config('permissions.PERMISSION_FINANZAS.ver'),
            config('permissions.PERMISSION_FINANZAS.reporteria.ver')
        ]);

        // Gerente Ventas
        $gerenteVentas = Role::findByName(config('constants.ROL_USUARIO_SUPERVISOR', 'usuario_supervisor'));
        $gerenteVentas->givePermissionTo([
            // Productos
            config('permissions.PERMISSION_PRODUCTOS.ver'),
            config('permissions.PERMISSION_PRODUCTOS.inventario.ver'),
            // Ventas - Control Total
            config('permissions.PERMISSION_VENTAS.ver'),
            config('permissions.PERMISSION_VENTAS.crear'),
            config('permissions.PERMISSION_VENTAS.editar'),
            config('permissions.PERMISSION_VENTAS.eliminar'),
            config('permissions.PERMISSION_VENTAS.registros.ver'),
            config('permissions.PERMISSION_VENTAS.registros.crear'),
            config('permissions.PERMISSION_VENTAS.registros.editar'),
            config('permissions.PERMISSION_VENTAS.registros.eliminar'),
            config('permissions.PERMISSION_VENTAS.cotizaciones.ver'),
            config('permissions.PERMISSION_VENTAS.cotizaciones.crear'),
            config('permissions.PERMISSION_VENTAS.cotizaciones.editar'),
            config('permissions.PERMISSION_VENTAS.cotizaciones.eliminar'),
            config('permissions.PERMISSION_VENTAS.clientes.ver'),
            config('permissions.PERMISSION_VENTAS.clientes.crear'),
            config('permissions.PERMISSION_VENTAS.clientes.editar'),
            config('permissions.PERMISSION_VENTAS.clientes.eliminar'),
            config('permissions.PERMISSION_VENTAS.canales_venta.ver'),
            config('permissions.PERMISSION_VENTAS.canales_venta.crear'),
            config('permissions.PERMISSION_VENTAS.canales_venta.editar'),
            config('permissions.PERMISSION_VENTAS.canales_venta.eliminar'),
            config('permissions.PERMISSION_VENTAS.formas_pago.ver'),
            config('permissions.PERMISSION_VENTAS.formas_pago.crear'),
            config('permissions.PERMISSION_VENTAS.formas_pago.editar'),
            config('permissions.PERMISSION_VENTAS.formas_pago.eliminar'),
            // Finanzas - Reportes
            config('permissions.PERMISSION_FINANZAS.reporteria.ver')
        ]);

        // Gerente Operaciones
        $gerenteOperaciones = Role::findByName(config('constants.ROL_GERENTE_OPERACIONES', 'gerente_operaciones'));
        $gerenteOperaciones->givePermissionTo([
            // Productos - Control Total
            config('permissions.PERMISSION_PRODUCTOS.ver'),
            config('permissions.PERMISSION_PRODUCTOS.crear'),
            config('permissions.PERMISSION_PRODUCTOS.editar'),
            config('permissions.PERMISSION_PRODUCTOS.eliminar'),
            // Inventario
            config('permissions.PERMISSION_PRODUCTOS.inventario.ver'),
            config('permissions.PERMISSION_PRODUCTOS.inventario.crear'),
            config('permissions.PERMISSION_PRODUCTOS.inventario.editar'),
            config('permissions.PERMISSION_PRODUCTOS.inventario.eliminar'),
            // Bodegas
            config('permissions.PERMISSION_PRODUCTOS.bodegas.ver'),
            config('permissions.PERMISSION_PRODUCTOS.bodegas.crear'),
            config('permissions.PERMISSION_PRODUCTOS.bodegas.editar'),
            config('permissions.PERMISSION_PRODUCTOS.bodegas.eliminar'),
            // Compras y Gastos - Aprobaciones
            config('permissions.PERMISSION_COMPRAS.ver'),
            config('permissions.PERMISSION_COMPRAS.editar'),
            config('permissions.PERMISSION_GASTOS.ver'),
            config('permissions.PERMISSION_GASTOS.editar'),
            // Reportes
            config('permissions.PERMISSION_FINANZAS.reporteria.ver')
        ]);

        // Gerente Compras
        $gerenteCompras = Role::findByName(config('constants.ROL_GERENTE_COMPRAS', 'gerente_compras'));
        $gerenteCompras->givePermissionTo([
            // Compras - Control Total
            config('permissions.PERMISSION_COMPRAS.ver'),
            config('permissions.PERMISSION_COMPRAS.crear'),
            config('permissions.PERMISSION_COMPRAS.editar'),
            config('permissions.PERMISSION_COMPRAS.eliminar'),
            config('permissions.PERMISSION_COMPRAS.registros.ver'),
            config('permissions.PERMISSION_COMPRAS.registros.crear'),
            config('permissions.PERMISSION_COMPRAS.registros.editar'),
            config('permissions.PERMISSION_COMPRAS.registros.eliminar'),
            config('permissions.PERMISSION_COMPRAS.ordenes_compra.ver'),
            config('permissions.PERMISSION_COMPRAS.ordenes_compra.crear'),
            config('permissions.PERMISSION_COMPRAS.ordenes_compra.editar'),
            config('permissions.PERMISSION_COMPRAS.ordenes_compra.eliminar'),
            config('permissions.PERMISSION_COMPRAS.proveedores.ver'),
            config('permissions.PERMISSION_COMPRAS.proveedores.crear'),
            config('permissions.PERMISSION_COMPRAS.proveedores.editar'),
            config('permissions.PERMISSION_COMPRAS.proveedores.eliminar'),
            config('permissions.PERMISSION_COMPRAS.retaceo.ver'),
            config('permissions.PERMISSION_COMPRAS.retaceo.crear'),
            config('permissions.PERMISSION_COMPRAS.retaceo.editar'),
            config('permissions.PERMISSION_COMPRAS.retaceo.eliminar'),
            // Reportes
            config('permissions.PERMISSION_FINANZAS.reporteria.ver')
        ]);

        // Usuario Regular
        $usuario = Role::findByName(config('constants.ROL_USUARIO', 'usuario'));
        $usuario->givePermissionTo([
            config('permissions.PERMISSION_PRODUCTOS.ver'),
            config('permissions.PERMISSION_PRODUCTOS.inventario.ver'),
            config('permissions.PERMISSION_VENTAS.registros.crear'),
            config('permissions.PERMISSION_VENTAS.clientes.ver')
        ]);

        // Usuario Ventas
        $usuarioVentas = Role::findByName(config('constants.ROL_USUARIO_VENTAS', 'usuario_ventas'));
        $usuarioVentas->givePermissionTo([
            // Productos
            config('permissions.PERMISSION_PRODUCTOS.ver'),
            config('permissions.PERMISSION_PRODUCTOS.inventario.ver'),
            // Ventas
            config('permissions.PERMISSION_VENTAS.registros.ver'),
            config('permissions.PERMISSION_VENTAS.registros.crear'),
            config('permissions.PERMISSION_VENTAS.clientes.ver'),
            config('permissions.PERMISSION_FINANZAS.cierre_caja.ver'),
            // Servicios
            config('permissions.PERMISSION_SERVICIOS.ver'),
            // Citas si están habilitadas
            config('permissions.PERMISSION_CITAS.ver')
        ]);

        // Usuario Citas
        $usuarioCitas = Role::findByName(config('constants.ROL_USUARIO_CITAS', 'usuario_citas'));
        $usuarioCitas->givePermissionTo([
            config('permissions.PERMISSION_SERVICIOS.ver'),
            config('permissions.PERMISSION_SERVICIOS.crear'),
            config('permissions.PERMISSION_SERVICIOS.editar'),
            config('permissions.PERMISSION_VENTAS.registros.crear'),
            config('permissions.PERMISSION_VENTAS.registros.ver'),
            config('permissions.PERMISSION_VENTAS.clientes.ver'),
            config('permissions.PERMISSION_CITAS.ver'),
            config('permissions.PERMISSION_CITAS.crear'),
            config('permissions.PERMISSION_CITAS.editar'),
            config('permissions.PERMISSION_FINANZAS.cierre_caja.ver')
        ]);

        // Usuario Consultas
        $usuarioConsultas = Role::findByName(config('constants.ROL_USUARIO_CONSULTAS', 'usuario_consultas'));
        $usuarioConsultas->givePermissionTo([
            // Productos
            config('permissions.PERMISSION_PRODUCTOS.ver'),
            config('permissions.PERMISSION_PRODUCTOS.inventario.ver'),
            config('permissions.PERMISSION_PRODUCTOS.bodegas.ver'),
            config('permissions.PERMISSION_PRODUCTOS.categorias.ver'),
            // Ventas
            config('permissions.PERMISSION_VENTAS.ver'),
            config('permissions.PERMISSION_VENTAS.registros.ver'),
            config('permissions.PERMISSION_VENTAS.cotizaciones.ver'),
            config('permissions.PERMISSION_VENTAS.clientes.ver'),
            config('permissions.PERMISSION_VENTAS.canales_venta.ver'),
            config('permissions.PERMISSION_VENTAS.formas_pago.ver'),
            // Compras
            config('permissions.PERMISSION_COMPRAS.ver'),
            config('permissions.PERMISSION_COMPRAS.registros.ver'),
            config('permissions.PERMISSION_COMPRAS.ordenes_compra.ver'),
            config('permissions.PERMISSION_COMPRAS.proveedores.ver'),
            // Gastos
            config('permissions.PERMISSION_GASTOS.ver'),
            config('permissions.PERMISSION_GASTOS.registros.ver'),
            config('permissions.PERMISSION_GASTOS.categorias.ver'),
            // Servicios y Citas
            config('permissions.PERMISSION_SERVICIOS.ver'),
            config('permissions.PERMISSION_CITAS.ver'),
            // Finanzas
            config('permissions.PERMISSION_FINANZAS.ver'),
            config('permissions.PERMISSION_FINANZAS.reporteria.ver'),
            config('permissions.PERMISSION_FINANZAS.cierre_caja.ver'),
            config('permissions.PERMISSION_FINANZAS.documentos.ver'),
            // Ayuda
            config('permissions.PERMISSION_AYUDA.ver')
        ]);

        
    }
}
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    public function run()
    {
       


        // Super Admin - Acceso Total
        $superAdmin = Role::updateOrCreate(['name' => config('constants.ROL_SUPER_ADMIN')]);
        $superAdmin->givePermissionTo(Permission::all());

        // Contador Superior
        $contadorSuperior = Role::updateOrCreate(['name' => config('constants.ROL_CONTADOR_SUPERIOR')]);
        $contadorSuperior->givePermissionTo([
            // Permisos de Ventas - Solo Ver
            config('permissions.PERMISSION_VENTAS.PERMISSION_VER_VENTAS'),
            config('permissions.PERMISSION_VENTAS.PERMISSION_VER_DEVOLUCIONES'),
            config('permissions.PERMISSION_VENTAS.PERMISSION_VER_COTIZACIONES'),
            // Permisos de Compras
            config('permissions.PERMISSION_COMPRAS.PERMISSION_VER_COMPRAS'),
            config('permissions.PERMISSION_COMPRAS.PERMISSION_APROBAR_COMPRAS'),
            config('permissions.PERMISSION_COMPRAS.PERMISSION_GESTIONAR_DEVOLUCIONES_COMPRAS'),
            // Permisos de Gastos
            config('permissions.PERMISSION_GASTOS.PERMISSION_VER_GASTOS'),
            config('permissions.PERMISSION_GASTOS.PERMISSION_APROBAR_GASTOS'),
            config('permissions.PERMISSION_GASTOS.PERMISSION_GESTIONAR_DEVOLUCIONES_GASTOS'),
            // Reportes
            config('permissions.PERMISSION_REPORTES.PERMISSION_VER_REPORTES'),
            config('permissions.PERMISSION_REPORTES.PERMISSION_GENERAR_REPORTES'),
            config('permissions.PERMISSION_REPORTES.PERMISSION_EXPORTAR_REPORTES'),
            // Configuración específica
            config('permissions.PERMISSION_CONFIGURACION.PERMISSION_VER_CONFIGURACION')
        ]);

        // Contador Auxiliar
        $contadorAuxiliar = Role::updateOrCreate(['name' => config('constants.ROL_CONTADOR_AUXILIAR')]);
        $contadorAuxiliar->givePermissionTo([
            config('permissions.PERMISSION_VENTAS.PERMISSION_VER_VENTAS'),
            config('permissions.PERMISSION_COMPRAS.PERMISSION_VER_COMPRAS'),
            config('permissions.PERMISSION_GASTOS.PERMISSION_VER_GASTOS'),
            config('permissions.PERMISSION_REPORTES.PERMISSION_VER_REPORTES'),
            config('permissions.PERMISSION_CONFIGURACION.PERMISSION_VER_CONFIGURACION')
        ]);

        // Gerente Ventas
        $gerenteVentas = Role::updateOrCreate(['name' => config('constants.ROL_GERENTE_VENTAS')]);
        $gerenteVentas->givePermissionTo([
            // Inventario
            config('permissions.PERMISSION_INVENTARIO.PERMISSION_VER_PRODUCTOS'),
            config('permissions.PERMISSION_INVENTARIO.PERMISSION_GESTIONAR_CONSIGNACION'),
            // Ventas - Control Total
            config('permissions.PERMISSION_VENTAS.PERMISSION_VER_VENTAS'),
            config('permissions.PERMISSION_VENTAS.PERMISSION_REGISTRAR_VENTAS'),
            config('permissions.PERMISSION_VENTAS.PERMISSION_ANULAR_VENTAS'),
            config('permissions.PERMISSION_VENTAS.PERMISSION_GESTIONAR_PROMOCIONES'),
            config('permissions.PERMISSION_VENTAS.PERMISSION_GESTIONAR_DEVOLUCIONES'),
            config('permissions.PERMISSION_VENTAS.PERMISSION_GESTIONAR_COTIZACIONES'),
            // Mi Negocio
            config('permissions.PERMISSION_MI_NEGOCIO.PERMISSION_GESTIONAR_CLIENTES'),
            config('permissions.PERMISSION_MI_NEGOCIO.PERMISSION_GESTIONAR_CANALES_VENTA'),
            // Reportes
            config('permissions.PERMISSION_REPORTES.PERMISSION_VER_REPORTES'),
            config('permissions.PERMISSION_REPORTES.PERMISSION_GENERAR_REPORTES')
        ]);

        // Gerente Operaciones
        $gerenteOperaciones = Role::updateOrCreate(['name' => config('constants.ROL_GERENTE_OPERACIONES')]);
        $gerenteOperaciones->givePermissionTo([
            // Inventario - Control Total
            config('permissions.PERMISSION_INVENTARIO.PERMISSION_VER_PRODUCTOS'),
            config('permissions.PERMISSION_INVENTARIO.PERMISSION_GESTIONAR_CONSIGNACION'),
            config('permissions.PERMISSION_INVENTARIO.PERMISSION_GESTIONAR_MATERIA_PRIMA'),
            config('permissions.PERMISSION_INVENTARIO.PERMISSION_REALIZAR_AJUSTES'),
            config('permissions.PERMISSION_INVENTARIO.PERMISSION_GESTIONAR_TRASLADOS'),
            // Operaciones
            config('permissions.PERMISSION_COMPRAS.PERMISSION_APROBAR_COMPRAS'),
            config('permissions.PERMISSION_GASTOS.PERMISSION_APROBAR_GASTOS'),
            // Reportes
            config('permissions.PERMISSION_REPORTES.PERMISSION_VER_REPORTES'),
            config('permissions.PERMISSION_REPORTES.PERMISSION_GENERAR_REPORTES')
        ]);

        // Gerente Compras
        $gerenteCompras = Role::updateOrCreate(['name' => config('constants.ROL_GERENTE_COMPRAS')]);
        $gerenteCompras->givePermissionTo([
            // Compras - Control Total
            config('permissions.PERMISSION_COMPRAS.PERMISSION_VER_COMPRAS'),
            config('permissions.PERMISSION_COMPRAS.PERMISSION_REGISTRAR_COMPRAS'),
            config('permissions.PERMISSION_COMPRAS.PERMISSION_APROBAR_COMPRAS'),
            config('permissions.PERMISSION_COMPRAS.PERMISSION_GESTIONAR_DEVOLUCIONES_COMPRAS'),
            // Proveedores
            config('permissions.PERMISSION_MI_NEGOCIO.PERMISSION_VER_PROVEEDORES'),
            config('permissions.PERMISSION_MI_NEGOCIO.PERMISSION_GESTIONAR_PROVEEDORES'),
            // Reportes
            config('permissions.PERMISSION_REPORTES.PERMISSION_VER_REPORTES'),
            config('permissions.PERMISSION_REPORTES.PERMISSION_GENERAR_REPORTES')
        ]);

        // Usuario Regular
        $usuario = Role::updateOrCreate(['name' => config('constants.ROL_USUARIO')]);
        $usuario->givePermissionTo([
            config('permissions.PERMISSION_INVENTARIO.PERMISSION_VER_PRODUCTOS'),
            config('permissions.PERMISSION_VENTAS.PERMISSION_REGISTRAR_VENTAS'),
            config('permissions.PERMISSION_MI_NEGOCIO.PERMISSION_VER_CLIENTES')
        ]);

        // Usuario Ventas
        $usuarioVentas = Role::updateOrCreate(['name' => config('constants.ROL_USUARIO_VENTAS')]);
        $usuarioVentas->givePermissionTo([
            // Inventario
            config('permissions.PERMISSION_INVENTARIO.PERMISSION_VER_PRODUCTOS'),
            config('permissions.PERMISSION_INVENTARIO.PERMISSION_VER_CONSIGNACION'),
            // Ventas
            config('permissions.PERMISSION_VENTAS.PERMISSION_REGISTRAR_VENTAS'),
            config('permissions.PERMISSION_VENTAS.PERMISSION_VER_PROMOCIONES'),
            config('permissions.PERMISSION_VENTAS.PERMISSION_VER_DEVOLUCIONES'),
            // Mi Negocio
            config('permissions.PERMISSION_MI_NEGOCIO.PERMISSION_VER_CATEGORIAS'),
            config('permissions.PERMISSION_MI_NEGOCIO.PERMISSION_VER_CLIENTES'),
            config('permissions.PERMISSION_MI_NEGOCIO.PERMISSION_VER_CIERRE_CAJA'),
            // Citas
            config('permissions.PERMISSION_CITAS_SERVICIOS.PERMISSION_VER_CITAS')
        ]);

        // Usuario Citas
        $usuarioCitas = Role::updateOrCreate(['name' => config('constants.ROL_USUARIO_CITAS')]);
        $usuarioCitas->givePermissionTo([
            config('permissions.PERMISSION_CITAS_SERVICIOS.PERMISSION_GESTIONAR_SERVICIOS'),
            config('permissions.PERMISSION_VENTAS.PERMISSION_REGISTRAR_VENTAS'),
            config('permissions.PERMISSION_VENTAS.PERMISSION_VER_PROMOCIONES'),
            config('permissions.PERMISSION_VENTAS.PERMISSION_VER_DEVOLUCIONES'),
            config('permissions.PERMISSION_VENTAS.PERMISSION_VER_COTIZACIONES'),
            config('permissions.PERMISSION_MI_NEGOCIO.PERMISSION_VER_CLIENTES'),
            config('permissions.PERMISSION_MI_NEGOCIO.PERMISSION_VER_CIERRE_CAJA')
        ]);

        // Usuario Consultas
        $usuarioConsultas = Role::updateOrCreate(['name' => config('constants.ROL_USUARIO_CONSULTAS')]);
        $usuarioConsultas->givePermissionTo([
            config('permissions.PERMISSION_INVENTARIO.PERMISSION_VER_PRODUCTOS'),
            config('permissions.PERMISSION_INVENTARIO.PERMISSION_VER_CONSIGNACION'),
            config('permissions.PERMISSION_INVENTARIO.PERMISSION_VER_MATERIA_PRIMA'),
            config('permissions.PERMISSION_VENTAS.PERMISSION_VER_VENTAS'),
            config('permissions.PERMISSION_VENTAS.PERMISSION_VER_PROMOCIONES'),
            config('permissions.PERMISSION_VENTAS.PERMISSION_VER_DEVOLUCIONES'),
            config('permissions.PERMISSION_VENTAS.PERMISSION_VER_COTIZACIONES'),
            config('permissions.PERMISSION_COMPRAS.PERMISSION_VER_COMPRAS'),
            config('permissions.PERMISSION_COMPRAS.PERMISSION_VER_DEVOLUCIONES_COMPRAS'),
            config('permissions.PERMISSION_GASTOS.PERMISSION_VER_GASTOS'),
            config('permissions.PERMISSION_GASTOS.PERMISSION_VER_DEVOLUCIONES_GASTOS'),
            config('permissions.PERMISSION_CITAS_SERVICIOS.PERMISSION_VER_CITAS'),
            config('permissions.PERMISSION_REPORTES.PERMISSION_VER_REPORTES'),
            config('permissions.PERMISSION_MI_NEGOCIO.PERMISSION_VER_CATEGORIAS'),
            config('permissions.PERMISSION_MI_NEGOCIO.PERMISSION_VER_CLIENTES'),
            config('permissions.PERMISSION_MI_NEGOCIO.PERMISSION_VER_PROVEEDORES'),
            config('permissions.PERMISSION_MI_NEGOCIO.PERMISSION_VER_CANALES_VENTA'),
            config('permissions.PERMISSION_MI_NEGOCIO.PERMISSION_VER_DOCUMENTOS'),
            config('permissions.PERMISSION_MI_NEGOCIO.PERMISSION_VER_FORMAS_PAGO'),
            config('permissions.PERMISSION_MI_NEGOCIO.PERMISSION_VER_CIERRE_CAJA'),
            config('permissions.PERMISSION_CONFIGURACION.PERMISSION_VER_USUARIOS'),
            config('permissions.PERMISSION_CONFIGURACION.PERMISSION_VER_SUSCRIPCION'),
            config('permissions.PERMISSION_CONFIGURACION.PERMISSION_VER_CONFIGURACION'),
            config('permissions.PERMISSION_CONFIGURACION.PERMISSION_VER_RECORDATORIOS'),
            config('permissions.PERMISSION_CONFIGURACION.PERMISSION_VER_PRESUPUESTOS')
        ]);
    }
}

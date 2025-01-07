<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $superAdminRole = Role::updateOrCreate(['name' => config('constants.ROL_SUPER_ADMIN')]);
        $superAdminRole->givePermissionTo(Permission::all());
        // Contadores
        $contadorSuperiorRole = Role::updateOrCreate(['name' => config('constants.ROL_CONTADOR_SUPERIOR')]);
        $contadorSuperiorRole->givePermissionTo([
            config('permissions.PERMISSIONS_APROBAR_MOVIMIENTOS'),
            config('permissions.PERMISSIONS_REGISTRAR_MOVIMIENTOS'),
            config('permissions.PERMISSIONS_VER_REPORTES'),
            config('permissions.PERMISSIONS_MODULO_DASHBOARD'),
        ]);
        $contadorAuxiliarRole = Role::updateOrCreate(['name' => config('constants.ROL_CONTADOR_AUXILIAR')]);
        $contadorAuxiliarRole->givePermissionTo([
            config('permissions.PERMISSIONS_REGISTRAR_MOVIMIENTOS'),
            config('permissions.PERMISSIONS_VER_INFORMACION_BASICA'),
        ]);

        // Gerentes
        $gerenteVentasRole = Role::updateOrCreate(['name' => config('constants.ROL_GERENTE_VENTAS')]);
        $gerenteVentasRole->givePermissionTo([
            config('permissions.PERMISSIONS_APROBAR_VENTAS'),
            config('permissions.PERMISSIONS_VER_RESULTADOS'),
            config('permissions.PERMISSIONS_MODULO_DASHBOARD'),
        ]);
        $gerenteOperacionesRole = Role::updateOrCreate(['name' => config('constants.ROL_GERENTE_OPERACIONES')]);
        $gerenteOperacionesRole->givePermissionTo([
            config('permissions.PERMISSIONS_APROBAR_OPERACIONES'),
            config('permissions.PERMISSIONS_VER_RESULTADOS'),
            config('permissions.PERMISSIONS_MODULO_DASHBOARD'),
        ]);
        $gerenteComprasRole = Role::updateOrCreate(['name' => config('constants.ROL_GERENTE_COMPRAS')]);
        $gerenteComprasRole->givePermissionTo([
            config('permissions.PERMISSIONS_APROBAR_COMPRAS'),
            config('permissions.PERMISSIONS_VER_RESULTADOS'),
            config('permissions.PERMISSIONS_MODULO_DASHBOARD'),
        ]);

        // Usuario Regular
        $usuarioRole = Role::updateOrCreate(['name' => config('constants.ROL_USUARIO')]);
        $usuarioRole->givePermissionTo([
            config('permissions.PERMISSIONS_REGISTRAR_INFORMACION'),
            config('permissions.PERMISSIONS_VER_INFORMACION_BASICA'),
        ]);
    }
}

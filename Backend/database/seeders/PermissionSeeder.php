<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
   
        foreach (config('permissions.PERMISSION_INVENTARIO') as $permission) {
            Permission::updateOrCreate(['name' => $permission]);
        }
        foreach (config('permissions.PERMISSION_VENTAS') as $permission) {
            Permission::updateOrCreate(['name' => $permission]);
        }

        foreach (config('permissions.PERMISSION_COMPRAS') as $permission) {
            Permission::updateOrCreate(['name' => $permission]);
        }
        foreach (config('permissions.PERMISSION_GASTOS') as $permission) {
            Permission::updateOrCreate(['name' => $permission]);
        }

        foreach (config('permissions.PERMISSION_CITAS_SERVICIOS') as $permission) {
            Permission::updateOrCreate(['name' => $permission]);
        }
        foreach (config('permissions.PERMISSION_MI_NEGOCIO') as $permission) {
            Permission::updateOrCreate(['name' => $permission]);
        }
        foreach (config('permissions.PERMISSION_CONFIGURACION') as $permission) {
            Permission::updateOrCreate(['name' => $permission]);
        }
        foreach (config('permissions.PERMISSION_REPORTES') as $permission) {
            Permission::updateOrCreate(['name' => $permission]);
        }
    }
}

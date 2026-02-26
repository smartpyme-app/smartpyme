<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Contracts\Permission;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->call([
            //MHTableSeeder::class,
            PermissionSeeder::class,
            RoleSeeder::class,
            UserRoleSeeder::class,
            FidelizacionFuncionalidadSeeder::class,
            // PaquetesTableSeeder::class,
            // EmpresaTableSeeder::class,
            // UsersTableSeeder::class,
            // CategoriasTableSeeder::class,
            // ClientesTableSeeder::class,
            // ProveedoresTableSeeder::class,
            // ProductosTableSeeder::class,
            // EmpleadosTableSeeder::class,
            // FlotasTableSeeder::class,
            // FletesTableSeeder::class,
            // MantenimientosTableSeeder::class,

            // OrdenesTableSeeder::class,
            // VentasTableSeeder::class,
            // ComprasTableSeeder::class,
            // GastosTableSeeder::class,

        ]);
    }
}
        

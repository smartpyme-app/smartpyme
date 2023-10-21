<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\Admin\Empleados\Asistencia;
use App\Models\Admin\Empleados\Planillas\Planilla;
use App\Models\Admin\Empleados\Planillas\Detalle;
use App\Models\User;

class UsersTableSeeder extends Seeder
{

    public function run()
    {
        $faker = \Faker\Factory::create();

            $user = new User;
            $user->name         = 'Admin';
            $user->username     = 'admin';
            $user->password     = Hash::make('admin');
            $user->tipo         = 'Administrador';
            $user->sucursal_id  = 1;
            $user->caja_id      = 1;
            $user->bodega_id    = 1;
            $user->save();

            $user = new User;
            $user->name         = 'Supervisor';
            $user->username     = 'supervisor';
            $user->password     = Hash::make('supervisor');
            $user->tipo         = 'Supervisor';
            $user->sucursal_id  = 1;
            $user->caja_id      = 1;
            $user->bodega_id    = 1;
            $user->save();

            $user = new User;
            $user->name         = 'Cajero';
            $user->username     = 'cajero';
            $user->password     = Hash::make('cajero');
            $user->tipo         = 'Cajero';
            $user->sucursal_id  = 1;
            $user->caja_id      = 1;
            $user->bodega_id    = 1;
            $user->save();

            $user = new User;
            $user->name         = 'Vendedor';
            $user->username     = 'vendedor';
            $user->password     = Hash::make('vendedor');
            $user->tipo         = 'Vendedor';
            $user->sucursal_id  = 1;
            $user->caja_id      = 1;
            $user->bodega_id    = 1;
            $user->save();
            
    }
}

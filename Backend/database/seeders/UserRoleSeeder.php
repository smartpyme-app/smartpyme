<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class UserRoleSeeder extends Seeder
{
    public function run()
    {
        //truncate roles
      //  Role::truncate();
        // Mapeo de tipos a roles según las constantes
        $tipoToRol = [
            'Super Administrador' => config('constants.ROL_SUPER_ADMIN'),
            'Administrador'  => config('constants.ROL_ADMIN'),
            'Operativo'    => config('constants.ROL_USUARIO'),
            'Ventas'       => config('constants.ROL_USUARIO_VENTAS'),
            'Operador'     => config('constants.ROL_GERENTE_OPERACIONES'),
            'Contador'     => config('constants.ROL_CONTADOR_SUPERIOR'),
            'Supervisor'   => config('constants.ROL_GERENTE_VENTAS'),
            'Citas'        => config('constants.ROL_USUARIO_CITAS'),
            'Cajero'       => config('constants.ROL_USUARIO_CAJERO'),
            'Vendedor'     => config('constants.ROL_USUARIO_VENDEDOR'),
        ];

        // Obtener todos los usuarios con tipo
        $usuarios = User::all()->where('tipo', '!=', '');
        $contador = 0;
        $rolesAsignados = [];

        foreach ($usuarios as $usuario) {
            if ($usuario->id_empresa == 2) {
                // Solo asignar rol super admin a usuarios de empresa 2
                $usuario->syncRoles([config('constants.ROL_SUPER_ADMIN')]);
                $contador++;
                $rolesAsignados[] = 'Super Administrador';
            } else if (isset($tipoToRol[$usuario->tipo])) {
                $usuario->assignRole($tipoToRol[$usuario->tipo]);
                $contador++;
                $rolesAsignados[] = $usuario->tipo;
            }
        }

        $this->command->info('Se asignaron roles a ' . $contador . ' usuarios');
        $this->command->info('Roles asignados: ' . implode(', ', array_unique($rolesAsignados)));
    }
}

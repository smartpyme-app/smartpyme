<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class UserRoleSeeder extends Seeder
{
    public function run()
    {

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
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
            'Contador Auxiliar' => config('constants.ROL_CONTADOR_AUXILIAR'),
            'Gerente Compras' => config('constants.ROL_GERENTE_COMPRAS'),
            'Gerente Ventas' => config('constants.ROL_GERENTE_VENTAS'),
            'Gerente Operaciones' => config('constants.ROL_GERENTE_OPERACIONES'),
            'Supervisor Limitado' => config('constants.ROL_SUPERVISOR_LIMITADO'),
            'Usuario Consultas' => config('constants.ROL_USUARIO_CONSULTAS'),
            'Citas'        => config('constants.ROL_USUARIO_CITAS'),
            'Supervisor'   => config('constants.ROL_USUARIO_SUPERVISOR'),
            // 'Cajero'       => config('constants.ROL_USUARIO_CAJERO'),
            // 'Vendedor'     => config('constants.ROL_USUARIO_VENDEDOR'),
            // 'Cocinero'     => config('constants.ROL_USUARIO_COCINERO'),
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

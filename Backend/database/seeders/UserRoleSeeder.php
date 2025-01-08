<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class UserRoleSeeder extends Seeder
{
    public function run()
    {
        // Mapeo de tipos a roles según las constantes
        $tipoToRol = [
            'Administrador' => config('constants.ROL_SUPER_ADMIN'),
            'Operativo'    => config('constants.ROL_USUARIO'),
            'Ventas'       => config('constants.ROL_USUARIO_VENTAS'),
            'Operador'     => config('constants.ROL_GERENTE_OPERACIONES'),
            'Contador'     => config('constants.ROL_CONTADOR_SUPERIOR'),
            'Supervisor'   => config('constants.ROL_GERENTE_VENTAS'),
            'Citas'        => config('constants.ROL_USUARIO_CITAS')
        ];

        // Obtener todos los usuarios con tipo
        $usuarios = User::all()->where('tipo', '!=', '');
        $contador = 0;
        $rolesAsignados = [];

        foreach ($usuarios as $usuario) {
            if (isset($tipoToRol[$usuario->tipo])) {
                $usuario->assignRole($tipoToRol[$usuario->tipo]);
                $contador++;
                $rolesAsignados[] = $usuario->tipo;
            }
        }

        $this->command->info('Se asignaron roles a ' . $contador . ' usuarios');
        $this->command->info('Roles asignados: ' . implode(', ', array_unique($rolesAsignados)));
    }
}
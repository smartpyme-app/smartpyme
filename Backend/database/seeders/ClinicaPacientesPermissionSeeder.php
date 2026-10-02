<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ClinicaPacientesPermissionSeeder extends Seeder
{
    public const PERMISOS = [
        'clinica.pacientes.ver',
        'clinica.pacientes.crear',
        'clinica.pacientes.editar',
        'clinica.pacientes.desactivar',
    ];

    public function run(): void
    {
        $this->call(ClinicaFase1PermissionSeeder::class);
    }
}

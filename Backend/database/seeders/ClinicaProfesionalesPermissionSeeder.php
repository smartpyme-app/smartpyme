<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ClinicaProfesionalesPermissionSeeder extends Seeder
{
    public const PERMISOS = [
        'clinica.profesionales.ver',
        'clinica.profesionales.editar',
    ];

    public function run(): void
    {
        $this->call(ClinicaFase1PermissionSeeder::class);
    }
}

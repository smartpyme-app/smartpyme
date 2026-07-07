<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AuditoriaPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'auditoria.ver',
            'auditoria.plataforma.ver',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name]);
        }

        Role::findByName('admin')->givePermissionTo('auditoria.ver');
        Role::findByName('super_admin')->givePermissionTo(['auditoria.ver', 'auditoria.plataforma.ver']);
    }
}

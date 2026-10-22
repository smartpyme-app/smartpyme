<?php

use Database\Seeders\VentasLimitadoRoleSeeder;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

class AddVentasLimitadoRole extends Migration
{
    public function up(): void
    {
        (new VentasLimitadoRoleSeeder())->run();
    }

    public function down(): void
    {
        Role::query()
            ->where('name', 'ventas_limitado')
            ->where('guard_name', 'web')
            ->delete();
    }
}

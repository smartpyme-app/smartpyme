<?php

namespace Tests\Unit\Database\Seeders;

use App\Models\Admin\Module;
use App\Models\Admin\ModulePermission;
use Database\Seeders\ComprasEditarPrecioPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ComprasEditarPrecioPermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_permiso_y_lo_vincula_al_modulo_compras(): void
    {
        foreach (['super_admin', 'admin', 'usuario_supervisor'] as $roleName) {
            Role::findOrCreate($roleName, 'web');
        }

        $this->seed(ComprasEditarPrecioPermissionSeeder::class);

        $this->assertDatabaseHas('permissions', [
            'name' => ComprasEditarPrecioPermissionSeeder::EDITAR_PRECIO,
            'guard_name' => 'web',
        ]);

        $module = Module::where('name', 'compras')->first();
        $this->assertNotNull($module);

        $permission = Permission::findByName(ComprasEditarPrecioPermissionSeeder::EDITAR_PRECIO, 'web');
        $this->assertTrue(
            ModulePermission::where('module_id', $module->id)
                ->whereNull('submodule_id')
                ->where('permission_id', $permission->id)
                ->exists()
        );

        foreach (['super_admin', 'admin', 'usuario_supervisor'] as $roleName) {
            $role = Role::findByName($roleName, 'web');
            $this->assertTrue($role->hasPermissionTo(ComprasEditarPrecioPermissionSeeder::EDITAR_PRECIO));
        }
    }
}

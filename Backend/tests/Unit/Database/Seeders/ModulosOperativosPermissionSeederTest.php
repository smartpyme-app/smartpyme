<?php

namespace Tests\Unit\Database\Seeders;

use App\Models\Admin\Module;
use Database\Seeders\ModulosOperativosPermissionSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ModulosOperativosPermissionSeederTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        config()->set('permissions', require config_path('permissions.php'));
        DB::purge('sqlite');

        $this->createPermissionTables();
        $this->createModuleTables();
    }

    public function test_catalogo_define_permisos_operativos_y_conserva_planilla_completa(): void
    {
        foreach ([
            'PERMISSION_RESTAURANTE' => 'restaurante',
            'PERMISSION_PEDIDOS' => 'pedidos',
        ] as $configKey => $prefix) {
            $permissions = config("permissions.{$configKey}");

            $this->assertSame(['ver', 'crear', 'editar', 'eliminar'], array_keys($permissions));
            $this->assertSame([
                "{$prefix}.ver",
                "{$prefix}.crear",
                "{$prefix}.editar",
                "{$prefix}.eliminar",
            ], array_values($permissions));
        }

        $this->assertCount(28, collect(config('permissions.PERMISSION_PLANILLA'))->flatten());

        foreach ([
            'permissions.PERMISSION_VENTAS.consignas' => 'ventas.consignas',
            'permissions.PERMISSION_COMPRAS.consignas' => 'compras.consignas',
            'permissions.PERMISSION_ADMINISTRACION.departamentos' => 'administracion.departamentos',
            'permissions.PERMISSION_ADMINISTRACION.areas' => 'administracion.areas',
        ] as $configKey => $prefix) {
            $permissions = config($configKey);

            $this->assertSame(['ver', 'crear', 'editar', 'eliminar'], array_keys($permissions));
            $this->assertSame([
                "{$prefix}.ver",
                "{$prefix}.crear",
                "{$prefix}.editar",
                "{$prefix}.eliminar",
            ], array_values($permissions));
        }
        $this->assertNull(config('permissions.PERMISSION_CONSIGNAS'));
        $this->assertArrayNotHasKey('departamentos', config('permissions.PERMISSION_GASTOS'));
    }

    public function test_seeder_es_aditivo_idempotente_y_asigna_solo_roles_predeterminados(): void
    {
        foreach ([
            'super_admin',
            'admin',
            'usuario_supervisor',
            'contador_superior',
            'supervisor_limitado',
            'contador_auxiliar',
        ] as $roleName) {
            Role::create(['name' => $roleName, 'guard_name' => 'web']);
        }

        $legacyPermission = Permission::create(['name' => 'legacy.ver', 'guard_name' => 'web']);
        $legacyModule = Module::create([
            'name' => 'legacy',
            'display_name' => 'Legacy',
            'status' => true,
        ]);
        DB::table('module_permissions')->insert([
            'module_id' => $legacyModule->id,
            'submodule_id' => null,
            'permission_id' => $legacyPermission->id,
            'permission_type' => 'custom',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Role::findByName('supervisor_limitado')->givePermissionTo($legacyPermission);

        $this->seed(ModulosOperativosPermissionSeeder::class);
        $countsAfterFirstRun = [
            'permissions' => DB::table('permissions')->count(),
            'modules' => DB::table('modules')->count(),
            'submodules' => DB::table('submodules')->count(),
            'module_permissions' => DB::table('module_permissions')->count(),
            'role_permissions' => DB::table('role_has_permissions')->count(),
        ];
        $this->seed(ModulosOperativosPermissionSeeder::class);

        $this->assertSame($countsAfterFirstRun, [
            'permissions' => DB::table('permissions')->count(),
            'modules' => DB::table('modules')->count(),
            'submodules' => DB::table('submodules')->count(),
            'module_permissions' => DB::table('module_permissions')->count(),
            'role_permissions' => DB::table('role_has_permissions')->count(),
        ]);
        $this->assertSame(53, $countsAfterFirstRun['permissions']);
        $this->assertSame(7, $countsAfterFirstRun['modules']);
        $this->assertSame(10, $countsAfterFirstRun['submodules']);
        $this->assertSame(53, $countsAfterFirstRun['module_permissions']);

        $operationalPermissions = collect([
            config('permissions.PERMISSION_PLANILLA'),
            config('permissions.PERMISSION_RESTAURANTE'),
            config('permissions.PERMISSION_PEDIDOS'),
            config('permissions.PERMISSION_VENTAS.consignas'),
            config('permissions.PERMISSION_COMPRAS.consignas'),
            config('permissions.PERMISSION_ADMINISTRACION.departamentos'),
            config('permissions.PERMISSION_ADMINISTRACION.areas'),
        ])->flatten()->all();

        foreach (['super_admin', 'admin', 'usuario_supervisor', 'contador_superior'] as $roleName) {
            $this->assertEmpty(array_diff(
                $operationalPermissions,
                Role::findByName($roleName)->permissions()->pluck('name')->all()
            ));
        }
        foreach (['supervisor_limitado', 'contador_auxiliar'] as $roleName) {
            $this->assertEmpty(array_intersect(
                $operationalPermissions,
                Role::findByName($roleName)->permissions()->pluck('name')->all()
            ));
        }

        $this->assertDatabaseHas('permissions', ['name' => 'legacy.ver']);
        $this->assertDatabaseHas('modules', ['name' => 'legacy']);
        $this->assertDatabaseHas('module_permissions', [
            'module_id' => $legacyModule->id,
            'permission_id' => $legacyPermission->id,
            'permission_type' => 'custom',
        ]);
        $this->assertTrue(Role::findByName('supervisor_limitado')->hasPermissionTo('legacy.ver'));
    }

    public function test_seeder_copia_consignas_y_departamentos_al_rol_que_ya_los_tenia(): void
    {
        $rol = Role::create(['name' => 'cajero', 'guard_name' => 'web']);
        $rol->givePermissionTo([
            Permission::create(['name' => 'consignas.ver', 'guard_name' => 'web']),
            Permission::create(['name' => 'gastos.departamentos.editar', 'guard_name' => 'web']),
        ]);

        $this->seed(ModulosOperativosPermissionSeeder::class);

        $this->assertTrue($rol->hasPermissionTo('ventas.consignas.ver'));
        $this->assertTrue($rol->hasPermissionTo('compras.consignas.ver'));
        $this->assertTrue($rol->hasPermissionTo('administracion.departamentos.editar'));
        $this->assertTrue($rol->hasPermissionTo('administracion.areas.editar'));
        $this->assertFalse($rol->hasPermissionTo('ventas.consignas.crear'));
        $this->assertFalse($rol->hasPermissionTo('planilla.ver'));
    }

    public function test_seeder_ignora_roles_predeterminados_ausentes(): void
    {
        Role::create(['name' => 'admin', 'guard_name' => 'web']);

        $this->seed(ModulosOperativosPermissionSeeder::class);

        $this->assertTrue(Role::findByName('admin')->hasPermissionTo('pedidos.ver'));
    }

    public function test_role_seeder_incluye_defaults_operativos_para_instalaciones_nuevas(): void
    {
        $source = file_get_contents(database_path('seeders/RoleSeeder.php'));
        $contadorSuperior = Str::between($source, '// Contador Superior', '// Contador Auxiliar');
        $usuarioSupervisor = Str::between($source, '// Usuario Supervisor', '// Gerente Operaciones');

        foreach ([
            'PERMISSION_VENTAS.consignas',
            'PERMISSION_COMPRAS.consignas',
            'PERMISSION_ADMINISTRACION.departamentos',
            'PERMISSION_ADMINISTRACION.areas',
            'PERMISSION_RESTAURANTE',
            'PERMISSION_PEDIDOS',
        ] as $configKey) {
            foreach (array_keys(config("permissions.{$configKey}")) as $action) {
                $configCall = "config('permissions.{$configKey}.{$action}')";

                $this->assertStringContainsString($configCall, $contadorSuperior);
                $this->assertStringContainsString($configCall, $usuarioSupervisor);
            }
        }

        foreach ($this->configLeafPaths(config('permissions.PERMISSION_PLANILLA')) as $path) {
            $this->assertStringContainsString(
                "config('permissions.PERMISSION_PLANILLA.{$path}')",
                $usuarioSupervisor
            );
        }
    }

    private function configLeafPaths(array $tree, string $prefix = ''): array
    {
        $paths = [];

        foreach ($tree as $key => $value) {
            $path = ltrim("{$prefix}.{$key}", '.');
            if (is_array($value)) {
                array_push($paths, ...$this->configLeafPaths($value, $path));
            } else {
                $paths[] = $path;
            }
        }

        return $paths;
    }

    private function createPermissionTables(): void
    {
        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });
        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['permission_id', 'role_id']);
        });
    }

    private function createModuleTables(): void
    {
        Schema::create('modules', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('display_name');
            $table->text('description')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
        Schema::create('submodules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('module_id');
            $table->string('name');
            $table->string('display_name');
            $table->text('description')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
        Schema::create('module_permissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('module_id')->nullable();
            $table->foreignId('submodule_id')->nullable();
            $table->foreignId('permission_id');
            $table->string('permission_type')->default('base');
            $table->timestamps();
        });
    }
}

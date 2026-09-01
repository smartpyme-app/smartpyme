<?php

namespace Tests\Unit\Database\Seeders;

use App\Models\Admin\Module;
use App\Models\Admin\Submodule;
use Database\Seeders\VentasDescuentosPermissionSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VentasDescuentosPermissionSeederTest extends TestCase
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

    public function test_catalogo_define_aplicar_y_autorizar_en_descuentos(): void
    {
        $this->assertSame([
            'aplicar' => 'ventas.descuentos.aplicar',
            'autorizar' => 'ventas.descuentos.autorizar',
        ], config('permissions.PERMISSION_VENTAS.descuentos'));
    }

    public function test_role_seeder_asigna_autorizar_a_supervisores_y_no_al_cajero(): void
    {
        $source = file_get_contents(database_path('seeders/RoleSeeder.php'));
        $gerenteVentas = \Illuminate\Support\Str::between($source, '// Gerente Ventas', '// Usuario Supervisor');
        $usuarioSupervisor = \Illuminate\Support\Str::between($source, '// Usuario Supervisor', '// Gerente Operaciones');
        $usuarioVentas = \Illuminate\Support\Str::between($source, '// Usuario Ventas', '// Usuario Vendedor');
        $usuarioCitas = \Illuminate\Support\Str::between($source, '// Usuario Citas', '// Usuario Consultas');

        $this->assertStringContainsString("config('permissions.PERMISSION_VENTAS.descuentos.aplicar')", $gerenteVentas);
        $this->assertStringContainsString("config('permissions.PERMISSION_VENTAS.descuentos.autorizar')", $gerenteVentas);
        $this->assertStringContainsString("config('permissions.PERMISSION_VENTAS.descuentos.aplicar')", $usuarioSupervisor);
        $this->assertStringContainsString("config('permissions.PERMISSION_VENTAS.descuentos.autorizar')", $usuarioSupervisor);
        $this->assertStringContainsString("config('permissions.PERMISSION_VENTAS.descuentos.aplicar')", $usuarioCitas);
        $this->assertStringNotContainsString("config('permissions.PERMISSION_VENTAS.descuentos.autorizar')", $usuarioCitas);
        $this->assertStringContainsString("ventas.descuentos.autorizar", $usuarioVentas);
        $this->assertStringContainsString('continue', $usuarioVentas);
    }

    public function test_seeder_es_aditivo_idempotente_y_deja_aplicar_al_cajero(): void
    {
        foreach (['super_admin', 'admin', 'usuario_supervisor', 'gerente_ventas', 'usuario_ventas'] as $roleName) {
            Role::create(['name' => $roleName, 'guard_name' => 'web']);
        }

        $registrosCrear = Permission::create(['name' => 'ventas.registros.crear', 'guard_name' => 'web']);
        Role::findByName('usuario_ventas')->givePermissionTo($registrosCrear);

        $this->seed(VentasDescuentosPermissionSeeder::class);
        $countsAfterFirstRun = [
            'permissions' => DB::table('permissions')->count(),
            'modules' => DB::table('modules')->count(),
            'submodules' => DB::table('submodules')->count(),
            'module_permissions' => DB::table('module_permissions')->count(),
            'role_permissions' => DB::table('role_has_permissions')->count(),
        ];
        $this->seed(VentasDescuentosPermissionSeeder::class);

        $this->assertSame($countsAfterFirstRun, [
            'permissions' => DB::table('permissions')->count(),
            'modules' => DB::table('modules')->count(),
            'submodules' => DB::table('submodules')->count(),
            'module_permissions' => DB::table('module_permissions')->count(),
            'role_permissions' => DB::table('role_has_permissions')->count(),
        ]);

        $this->assertTrue(Permission::where('name', 'ventas.descuentos.aplicar')->exists());
        $this->assertTrue(Permission::where('name', 'ventas.descuentos.autorizar')->exists());
        $this->assertTrue(Module::where('name', 'ventas')->exists());
        $this->assertTrue(Submodule::where('name', 'descuentos')->exists());

        $cajero = Role::findByName('usuario_ventas');
        $this->assertTrue($cajero->hasPermissionTo('ventas.descuentos.aplicar'));
        $this->assertFalse($cajero->hasPermissionTo('ventas.descuentos.autorizar'));

        $this->assertTrue(Role::findByName('usuario_supervisor')->hasPermissionTo('ventas.descuentos.autorizar'));
        $this->assertTrue(Role::findByName('gerente_ventas')->hasPermissionTo('ventas.descuentos.autorizar'));
        $this->assertTrue(Role::findByName('admin')->hasPermissionTo('ventas.descuentos.autorizar'));
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

<?php

namespace Tests\Unit\Database\Seeders;

use App\Models\Admin\Submodule;
use Database\Seeders\KardexPermissionSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class KardexPermissionSeederTest extends TestCase
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

    public function test_seeder_muestra_kardex_y_lo_copia_a_quien_ve_productos(): void
    {
        $this->assertSame('productos.kardex.ver', config('permissions.PERMISSION_PRODUCTOS.kardex.ver'));

        $verProductos = Permission::create(['name' => 'productos.ver', 'guard_name' => 'web']);
        $conProductos = Role::create(['name' => 'usuario_supervisor', 'guard_name' => 'web']);
        $sinProductos = Role::create(['name' => 'usuario_citas', 'guard_name' => 'web']);
        $conProductos->givePermissionTo($verProductos);

        $this->seed(KardexPermissionSeeder::class);
        $counts = [
            'permissions' => DB::table('permissions')->count(),
            'submodules' => DB::table('submodules')->count(),
            'role_permissions' => DB::table('role_has_permissions')->count(),
        ];
        $this->seed(KardexPermissionSeeder::class);

        $this->assertSame($counts, [
            'permissions' => DB::table('permissions')->count(),
            'submodules' => DB::table('submodules')->count(),
            'role_permissions' => DB::table('role_has_permissions')->count(),
        ]);
        $this->assertTrue(Submodule::where('name', 'kardex')->where('display_name', 'Kardex')->exists());
        $this->assertTrue($conProductos->fresh()->hasPermissionTo('productos.kardex.ver'));
        $this->assertFalse($sinProductos->fresh()->hasPermissionTo('productos.kardex.ver'));
    }
}

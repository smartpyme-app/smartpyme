<?php

namespace Tests\Unit\Database\Seeders;

use Database\Seeders\ActualizacionMasivaProductosPermissionSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ActualizacionMasivaProductosPermissionSeederTest extends TestCase
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

    public function test_seeder_asigna_actualizacion_masiva_a_admin_y_super_admin(): void
    {
        Role::create(['name' => 'admin', 'guard_name' => 'web']);
        Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::create(['name' => 'usuario_ventas', 'guard_name' => 'web']);

        $this->seed(ActualizacionMasivaProductosPermissionSeeder::class);
        $count = DB::table('role_has_permissions')->count();
        $this->seed(ActualizacionMasivaProductosPermissionSeeder::class);

        $this->assertSame($count, DB::table('role_has_permissions')->count());
        $this->assertTrue(Role::findByName('admin')->hasPermissionTo('productos.actualizacion_masiva.ejecutar'));
        $this->assertTrue(Role::findByName('super_admin')->hasPermissionTo('productos.actualizacion_masiva.ejecutar'));
        $this->assertFalse(Role::findByName('usuario_ventas')->hasPermissionTo('productos.actualizacion_masiva.ejecutar'));
    }
}

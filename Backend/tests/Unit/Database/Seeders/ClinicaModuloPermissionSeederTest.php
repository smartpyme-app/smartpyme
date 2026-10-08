<?php

namespace Tests\Unit\Database\Seeders;

use App\Models\Admin\Module;
use App\Models\Admin\Submodule;
use Database\Seeders\ClinicaModuloPermissionSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClinicaModuloPermissionSeederTest extends TestCase
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
        $this->crearTablas();
        Role::create(['name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_registra_modulo_clinica_con_submodulos(): void
    {
        $this->seed(ClinicaModuloPermissionSeeder::class);

        $modulo = Module::where('name', 'clinica')->first();
        $this->assertNotNull($modulo);
        $this->assertSame('Clínica', $modulo->display_name);

        $submodulos = Submodule::where('module_id', $modulo->id)->pluck('name')->sort()->values()->all();
        $this->assertSame(['consultas', 'expediente', 'pacientes', 'profesionales'], $submodulos);

        $this->assertNotNull(Permission::findByName('clinica.expediente.ver', 'web'));
        $admin = Role::findByName('admin', 'web');
        $this->assertTrue($admin->hasPermissionTo('clinica.expediente.ver'));
    }

    private function crearTablas(): void
    {
        Schema::create('modules', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('display_name')->nullable();
            $table->string('description')->nullable();
            $table->boolean('status')->default(1);
            $table->timestamps();
        });
        Schema::create('submodules', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('module_id');
            $table->string('name');
            $table->string('display_name')->nullable();
            $table->string('description')->nullable();
            $table->boolean('status')->default(1);
            $table->timestamps();
        });
        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });
        Schema::create('module_permissions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('module_id')->nullable();
            $table->unsignedBigInteger('submodule_id')->nullable();
            $table->unsignedBigInteger('permission_id');
            $table->string('permission_type')->nullable();
            $table->timestamps();
        });
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });
        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['permission_id', 'role_id']);
        });
    }
}

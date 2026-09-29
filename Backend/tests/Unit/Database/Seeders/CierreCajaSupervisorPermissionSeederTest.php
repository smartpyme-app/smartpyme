<?php

namespace Tests\Unit\Database\Seeders;

use Database\Seeders\CierreCajaSupervisorPermissionSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CierreCajaSupervisorPermissionSeederTest extends TestCase
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
    }

    public function test_role_seeder_incluye_cierre_de_caja_en_supervisor(): void
    {
        $source = file_get_contents(database_path('seeders/RoleSeeder.php'));
        $usuarioSupervisor = \Illuminate\Support\Str::between($source, '// Usuario Supervisor', '// Gerente Operaciones');

        $this->assertStringContainsString(
            "config('permissions.PERMISSION_FINANZAS.cierre_caja.ver')",
            $usuarioSupervisor
        );
    }

    public function test_seeder_asigna_cierre_de_caja_al_supervisor_y_es_idempotente(): void
    {
        Role::create(['name' => 'usuario_supervisor', 'guard_name' => 'web']);

        $this->seed(CierreCajaSupervisorPermissionSeeder::class);
        $count = DB::table('role_has_permissions')->count();
        $this->seed(CierreCajaSupervisorPermissionSeeder::class);

        $this->assertSame($count, DB::table('role_has_permissions')->count());
        $this->assertTrue(
            Role::findByName('usuario_supervisor')->hasPermissionTo('finanzas.cierre_caja.ver')
        );
    }
}

<?php

namespace Tests\Unit\Database\Seeders;

use Database\Seeders\VentasLimitadoRoleSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class VentasLimitadoRoleSeederTest extends TestCase
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
        config()->set('constants.ROL_VENTAS_LIMITADO', 'ventas_limitado');
        config()->set('constants.ROL_USUARIO_VENTAS', 'usuario_ventas');
        config()->set('permissions.PERMISSION_VENTAS', [
            'ver' => 'ventas.ver',
            'descuentos' => [
                'aplicar' => 'ventas.descuentos.aplicar',
                'autorizar' => 'ventas.descuentos.autorizar',
            ],
        ]);
        DB::purge('sqlite');
        $this->createPermissionTables();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function test_copia_permisos_de_usuario_ventas_y_es_idempotente(): void
    {
        $ventas = Role::create(['name' => 'usuario_ventas', 'guard_name' => 'web']);
        $ver = Permission::create(['name' => 'ventas.ver', 'guard_name' => 'web']);
        $ventas->givePermissionTo($ver);

        $this->seed(VentasLimitadoRoleSeeder::class);
        $conteo = DB::table('role_has_permissions')->count();
        $this->seed(VentasLimitadoRoleSeeder::class);

        $this->assertSame(1, Role::where('name', 'ventas_limitado')->count());
        $this->assertSame($conteo, DB::table('role_has_permissions')->count());
        $this->assertTrue(Role::findByName('ventas_limitado')->hasPermissionTo('ventas.ver'));
    }

    public function test_sin_usuario_ventas_asigna_ventas_y_omite_autorizar_descuentos(): void
    {
        Permission::create(['name' => 'ventas.ver', 'guard_name' => 'web']);
        Permission::create(['name' => 'ventas.descuentos.aplicar', 'guard_name' => 'web']);
        Permission::create(['name' => 'ventas.descuentos.autorizar', 'guard_name' => 'web']);

        $this->seed(VentasLimitadoRoleSeeder::class);

        $rol = Role::findByName('ventas_limitado');
        $this->assertTrue($rol->hasPermissionTo('ventas.ver'));
        $this->assertTrue($rol->hasPermissionTo('ventas.descuentos.aplicar'));
        $this->assertFalse($rol->hasPermissionTo('ventas.descuentos.autorizar'));
    }

    public function test_el_rol_queda_mapeado_al_tipo_ventas_limitado(): void
    {
        $fuentes = [
            app_path('Models/User.php'),
            app_path('Http/Requests/Admin/Usuarios/StoreUsuarioRequest.php'),
            app_path('Listeners/Authorization/AuthorizationApprovedListener.php'),
            database_path('seeders/UserRoleSeeder.php'),
        ];

        foreach ($fuentes as $fuente) {
            $codigo = file_get_contents($fuente);
            $this->assertStringContainsString('ventas_limitado', $codigo, $fuente);
            $this->assertStringContainsString('Ventas Limitado', $codigo, $fuente);
        }
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
}

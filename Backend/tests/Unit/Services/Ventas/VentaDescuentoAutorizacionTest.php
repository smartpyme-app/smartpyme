<?php

namespace Tests\Unit\Services\Ventas;

use App\Exceptions\FacturacionException;
use App\Models\User;
use App\Services\Ventas\VentaDescuentoAutorizacion;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class VentaDescuentoAutorizacionTest extends TestCase
{
    private VentaDescuentoAutorizacion $servicio;

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

        Schema::dropAllTables();
        $this->createTables();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate(VentaDescuentoAutorizacion::PERMISO_APLICAR, 'web');
        Permission::findOrCreate(VentaDescuentoAutorizacion::PERMISO_AUTORIZAR, 'web');
        $this->servicio = new VentaDescuentoAutorizacion();
    }

    public function test_tiene_descuento_linea_detecta_monto_porcentaje_y_ignora_puntos(): void
    {
        $this->assertTrue(VentaDescuentoAutorizacion::tieneDescuentoLinea([['descuento' => 1]]));
        $this->assertTrue(VentaDescuentoAutorizacion::tieneDescuentoLinea([['descuento_porcentaje' => 10]]));
        $this->assertTrue(VentaDescuentoAutorizacion::tieneDescuentoLinea([['descuento_monto' => 0.01]]));
        $this->assertFalse(VentaDescuentoAutorizacion::tieneDescuentoLinea([['descuento' => 0, 'descuento_puntos' => 5]]));
        $this->assertFalse(VentaDescuentoAutorizacion::tieneDescuentoLinea([]));
    }

    public function test_cajero_con_aplicar_no_pide_autorizador(): void
    {
        $cajero = $this->crearUsuario(['email' => 'caja@t.test']);
        $cajero->givePermissionTo(VentaDescuentoAutorizacion::PERMISO_APLICAR);

        $id = $this->servicio->resolverIdAutorizador($cajero, $this->requestConDescuento());

        $this->assertNull($id);
    }

    public function test_cotizacion_no_pide_autorizador(): void
    {
        $cajero = $this->crearUsuario(['email' => 'caja@t.test']);
        $request = $this->requestConDescuento(['cotizacion' => 1]);

        $this->assertNull($this->servicio->resolverIdAutorizador($cajero, $request));
    }

    public function test_sin_descuento_de_linea_no_pide_autorizador(): void
    {
        $cajero = $this->crearUsuario(['email' => 'caja@t.test']);
        $request = Request::create('/', 'POST', ['detalles' => [['descuento' => 0]]]);

        $this->assertNull($this->servicio->resolverIdAutorizador($cajero, $request));
    }

    public function test_sin_aplicar_y_descuento_sin_payload_lanza_403_generico(): void
    {
        $cajero = $this->crearUsuario(['email' => 'caja@t.test']);

        try {
            $this->servicio->resolverIdAutorizador($cajero, $this->requestConDescuento());
            $this->fail('Debió lanzar FacturacionException');
        } catch (FacturacionException $e) {
            $this->assertSame(403, $e->httpStatus);
            $this->assertSame(VentaDescuentoAutorizacion::MSG_GENERICO, $e->getMessage());
        }
    }

    public function test_pin_valido_de_supervisor_con_autorizar_devuelve_su_id(): void
    {
        $cajero = $this->crearUsuario(['email' => 'caja@t.test', 'id_empresa' => 1]);
        $supervisor = $this->crearUsuario([
            'email' => 'jefe@t.test',
            'id_empresa' => 1,
            'codigo_autorizacion' => '1234',
        ]);
        $supervisor->givePermissionTo(VentaDescuentoAutorizacion::PERMISO_AUTORIZAR);

        $request = $this->requestConDescuento([
            'descuento_autorizacion' => ['usuario' => 'jefe@t.test', 'codigo' => '1234'],
        ]);

        $this->assertSame($supervisor->id, $this->servicio->resolverIdAutorizador($cajero, $request));
    }

    public function test_pin_malo_otra_empresa_o_sin_autorizar_usan_403_generico(): void
    {
        $cajero = $this->crearUsuario(['email' => 'caja@t.test', 'id_empresa' => 1]);
        $conCodigo = $this->crearUsuario([
            'email' => 'jefe@t.test',
            'id_empresa' => 1,
            'codigo_autorizacion' => '1234',
        ]);
        $conCodigo->givePermissionTo(VentaDescuentoAutorizacion::PERMISO_AUTORIZAR);
        $otraEmpresa = $this->crearUsuario([
            'email' => 'otro@t.test',
            'id_empresa' => 2,
            'codigo_autorizacion' => '1234',
        ]);
        $otraEmpresa->givePermissionTo(VentaDescuentoAutorizacion::PERMISO_AUTORIZAR);
        $sinPermiso = $this->crearUsuario([
            'email' => 'peon@t.test',
            'id_empresa' => 1,
            'codigo_autorizacion' => '1234',
        ]);

        foreach ([
            ['usuario' => 'jefe@t.test', 'codigo' => '9999'],
            ['usuario' => 'otro@t.test', 'codigo' => '1234'],
            ['usuario' => 'peon@t.test', 'codigo' => '1234'],
            ['usuario' => 'nadie@t.test', 'codigo' => '1234'],
        ] as $creds) {
            try {
                $this->servicio->resolverIdAutorizador($cajero, $this->requestConDescuento([
                    'descuento_autorizacion' => $creds,
                ]));
                $this->fail('Debió lanzar FacturacionException para '.json_encode($creds));
            } catch (FacturacionException $e) {
                $this->assertSame(403, $e->httpStatus);
                $this->assertSame(VentaDescuentoAutorizacion::MSG_GENERICO, $e->getMessage());
            }
        }
    }

    public function test_supervisor_sin_codigo_configurado_usa_mensaje_especifico(): void
    {
        $cajero = $this->crearUsuario(['email' => 'caja@t.test', 'id_empresa' => 1]);
        $supervisor = $this->crearUsuario([
            'email' => 'jefe@t.test',
            'id_empresa' => 1,
            'codigo_autorizacion' => null,
        ]);
        $supervisor->givePermissionTo(VentaDescuentoAutorizacion::PERMISO_AUTORIZAR);

        try {
            $this->servicio->resolverIdAutorizador($cajero, $this->requestConDescuento([
                'descuento_autorizacion' => ['usuario' => 'jefe@t.test', 'codigo' => '1234'],
            ]));
            $this->fail('Debió lanzar FacturacionException');
        } catch (FacturacionException $e) {
            $this->assertSame(403, $e->httpStatus);
            $this->assertSame(VentaDescuentoAutorizacion::MSG_SIN_CODIGO, $e->getMessage());
        }
    }

    private function requestConDescuento(array $extra = []): Request
    {
        return Request::create('/', 'POST', array_merge([
            'detalles' => [['descuento' => 5]],
        ], $extra));
    }

    private function crearUsuario(array $attrs): User
    {
        return User::create(array_merge([
            'name' => 'Test',
            'password' => 'secret',
            'id_empresa' => 1,
        ], $attrs));
    }

    private function createTables(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->string('codigo_autorizacion')->nullable();
            $table->timestamps();
        });
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
        Schema::create('model_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['permission_id', 'model_id', 'model_type']);
        });
        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['role_id', 'model_id', 'model_type']);
        });
        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['permission_id', 'role_id']);
        });
    }
}

<?php

namespace Tests\Unit\Finanzas;

use App\Http\Controllers\Api\Finanzas\ModeladoController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use JWTAuth;
use Tests\TestCase;

class ModeladoControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        Schema::create('modelado_clasificaciones', function ($table) {
            $table->id();
            $table->unsignedInteger('id_empresa');
            $table->string('clave', 120);
            $table->string('nombre', 200);
            $table->string('rubro', 40);
            $table->string('tipo', 20)->nullable();
            $table->decimal('monto', 15, 2)->nullable();
            $table->decimal('pct_ventas', 8, 4)->nullable();
            $table->string('dia_pago', 32)->nullable();
            $table->string('proveedor', 191)->nullable();
            $table->string('sucursal', 191)->nullable();
            $table->boolean('suspendido')->default(false);
            $table->timestamps();
        });

        Schema::create('modelado_calendario', function ($table) {
            $table->id();
            $table->unsignedInteger('id_empresa');
            $table->string('clave', 120)->nullable();
            $table->string('nombre', 200);
            $table->string('detalle', 200)->nullable();
            $table->decimal('monto', 15, 2);
            $table->string('frecuencia', 20);
            $table->string('dia', 32);
            $table->string('origen', 20)->default('manual');
            $table->timestamps();
        });

        Schema::create('modelado_presupuesto_repartos', function ($table) {
            $table->unsignedInteger('id_empresa');
            $table->unsignedSmallInteger('anio');
            $table->string('reparto', 20);
            $table->timestamps();
        });

        Schema::create('modelado_presupuesto_lineas', function ($table) {
            $table->unsignedInteger('id_empresa');
            $table->unsignedSmallInteger('anio');
            $table->string('rubro', 40);
            $table->decimal('monto_anual', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('modelado_estacionalidad', function ($table) {
            $table->unsignedInteger('id_empresa');
            $table->unsignedSmallInteger('anio');
            $table->unsignedTinyInteger('mes');
            $table->decimal('peso', 10, 4);
            $table->timestamps();
        });

        Schema::create('modelado_presupuesto_area_lineas', function ($table) {
            $table->unsignedInteger('id_empresa');
            $table->unsignedSmallInteger('anio');
            $table->unsignedInteger('id_area_empresa');
            $table->string('rubro', 40);
            $table->decimal('monto_anual', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('modelado_presupuesto_departamento_lineas', function ($table) {
            $table->unsignedInteger('id_empresa');
            $table->unsignedSmallInteger('anio');
            $table->unsignedInteger('id_departamento');
            $table->string('rubro', 40);
            $table->decimal('monto_anual', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('presupuestos', function ($table) {
            $table->id();
            $table->unsignedInteger('id_empresa');
            $table->unsignedInteger('id_proyecto')->nullable();
            $table->boolean('enable')->default(true);
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
        });

        JWTAuth::shouldReceive('parseToken->authenticate')->andReturn((object) ['id_empresa' => 7]);
    }

    public function test_guardar_presupuesto_persiste_areas(): void
    {
        $ctrl = new ModeladoController;

        $ctrl->guardarPresupuesto(Request::create('/finanzas/modelado/presupuesto', 'PUT', [
            'anio' => 2026,
            'reparto' => 'igual',
            'filas' => [
                ['rubro' => 'ventas', 'anual' => 120000],
                ['rubro' => 'alquiler', 'anual' => 18000],
                ['rubro' => 'planilla', 'anual' => 24000],
            ],
            'departamentos' => [
                [
                    'idDepartamento' => 5,
                    'filas' => [
                        ['rubro' => 'ventas', 'anual' => 90000],
                        ['rubro' => 'alquiler', 'anual' => 6000],
                    ],
                ],
            ],
            'areas' => [
                [
                    'idArea' => 11,
                    'filas' => [
                        ['rubro' => 'ventas', 'anual' => 80000],
                        ['rubro' => 'planilla', 'anual' => 15000],
                    ],
                ],
            ],
        ]));

        $this->assertEquals(80000, DB::table('modelado_presupuesto_area_lineas')
            ->where('id_empresa', 7)
            ->where('id_area_empresa', 11)
            ->where('rubro', 'ventas')
            ->value('monto_anual'));
        $this->assertEquals(6000, DB::table('modelado_presupuesto_departamento_lineas')
            ->where('id_empresa', 7)
            ->where('id_departamento', 5)
            ->where('rubro', 'alquiler')
            ->value('monto_anual'));
    }

    public function test_suspender_y_eliminar_sincronizan_calendario_y_dia(): void
    {
        $ctrl = new ModeladoController;

        $ctrl->guardarClasificacion(Request::create('/finanzas/modelado/clasificacion', 'PUT', [
            'clave' => 'm:internet',
            'nombre' => 'Internet',
            'rubro' => 'admin',
            'tipo' => 'fijo',
            'monto' => 60,
            'diaPago' => '15',
        ]));

        $calRes = $ctrl->crearCalendario(Request::create('/finanzas/modelado/calendario', 'POST', [
            'clave' => 'm:internet',
            'nombre' => 'Internet',
            'monto' => 60,
            'frecuencia' => 'mensual',
            'dia' => '15',
        ]));
        $calId = json_decode($calRes->getContent(), true)['id'];

        $ctrl->actualizarCalendario(Request::create('/finanzas/modelado/calendario/'.$calId, 'PUT', [
            'clave' => 'm:internet',
            'nombre' => 'Internet',
            'monto' => 65,
            'frecuencia' => 'mensual',
            'dia' => '20',
        ]), $calId);

        $this->assertSame('20', DB::table('modelado_clasificaciones')->where('clave', 'm:internet')->value('dia_pago'));

        $ctrl->guardarClasificacion(Request::create('/finanzas/modelado/clasificacion', 'PUT', [
            'clave' => 'm:internet',
            'nombre' => 'Internet',
            'rubro' => 'admin',
            'tipo' => 'fijo',
            'monto' => 65,
            'diaPago' => '20',
            'suspendido' => true,
        ]));

        $this->assertEquals(1, DB::table('modelado_clasificaciones')->where('clave', 'm:internet')->value('suspendido'));
        $this->assertSame(0, DB::table('modelado_calendario')->where('clave', 'm:internet')->count());

        $ctrl->crearCalendario(Request::create('/finanzas/modelado/calendario', 'POST', [
            'clave' => 'm:internet',
            'nombre' => 'Internet',
            'monto' => 65,
            'frecuencia' => 'mensual',
            'dia' => '20',
        ]));
        $ctrl->borrarClasificacion(Request::create('/finanzas/modelado/clasificacion/eliminar', 'POST', [
            'clave' => 'm:internet',
        ]));

        $this->assertSame(0, DB::table('modelado_clasificaciones')->where('clave', 'm:internet')->count());
        $this->assertSame(0, DB::table('modelado_calendario')->where('clave', 'm:internet')->count());
    }
}

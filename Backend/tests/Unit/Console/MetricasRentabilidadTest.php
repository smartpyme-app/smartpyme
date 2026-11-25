<?php

namespace Tests\Unit\Console;

use App\Console\Commands\CalcularMetricasEmpresas;
use App\Console\Commands\CalcularMetricasSucursales;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * La rentabilidad pedía gastos_categorias.es_costo_venta, columna que no existe:
 * fallaba cada noche y ninguna empresa guardaba sus métricas.
 * Rentabilidad = ventas − egresos no anulados − costo de venta (detalles_venta.total_costo).
 */
class MetricasRentabilidadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('gastos_categorias', function (Blueprint $t) {
            $t->id(); $t->string('nombre'); $t->integer('id_empresa');
        });
        Schema::create('ventas', function (Blueprint $t) {
            $t->id(); $t->integer('id_empresa'); $t->integer('id_sucursal'); $t->string('estado');
            $t->boolean('cotizacion')->default(0); $t->date('fecha');
            $t->decimal('sub_total', 12, 2); $t->decimal('total', 12, 2);
        });
        Schema::create('detalles_venta', function (Blueprint $t) {
            $t->id(); $t->integer('id_venta'); $t->decimal('total_costo', 12, 2);
        });
        Schema::create('devoluciones_venta', function (Blueprint $t) {
            $t->id(); $t->integer('id_venta'); $t->integer('id_empresa'); $t->integer('id_sucursal');
            $t->string('estado')->default('Pagada'); $t->boolean('enable')->default(1); $t->date('fecha');
            $t->decimal('sub_total', 12, 2)->default(0); $t->decimal('total', 12, 2)->default(0);
        });
        Schema::create('compras', function (Blueprint $t) {
            $t->id(); $t->integer('id_empresa'); $t->string('estado'); $t->date('fecha');
            $t->decimal('sub_total', 12, 2); $t->decimal('total', 12, 2);
        });
        Schema::create('devoluciones_compra', function (Blueprint $t) {
            $t->id(); $t->integer('id_empresa'); $t->boolean('enable')->default(1); $t->date('fecha');
            $t->decimal('sub_total', 12, 2)->default(0); $t->decimal('total', 12, 2)->default(0);
        });
        Schema::create('egresos', function (Blueprint $t) {
            $t->id(); $t->integer('id_empresa'); $t->integer('id_sucursal'); $t->integer('id_categoria')->nullable();
            $t->string('estado'); $t->date('fecha'); $t->decimal('sub_total', 12, 2); $t->decimal('total', 12, 2);
        });

        DB::table('ventas')->insert([
            ['id' => 1, 'id_empresa' => 7, 'id_sucursal' => 1, 'estado' => 'Pagada', 'fecha' => '2026-11-10', 'sub_total' => 1000, 'total' => 1130],
            ['id' => 2, 'id_empresa' => 7, 'id_sucursal' => 1, 'estado' => 'Anulada', 'fecha' => '2026-11-10', 'sub_total' => 500, 'total' => 565],
        ]);
        DB::table('detalles_venta')->insert(['id_venta' => 1, 'total_costo' => 400]);
        DB::table('egresos')->insert([
            ['id_empresa' => 7, 'id_sucursal' => 1, 'id_categoria' => 3, 'estado' => 'Pagado', 'fecha' => '2026-11-12', 'sub_total' => 100, 'total' => 113],
            ['id_empresa' => 7, 'id_sucursal' => 1, 'id_categoria' => 3, 'estado' => 'Anulado', 'fecha' => '2026-11-12', 'sub_total' => 999, 'total' => 999],
        ]);
    }

    public function test_rentabilidad_por_empresa_se_calcula_sin_es_costo_venta(): void
    {
        $r = $this->invocar(new CalcularMetricasEmpresas(), [7, '2026-11-01', '2026-11-30']);

        $this->assertEqualsWithDelta(500, $r['monto_sin_iva'], 0.001); // 1000 − 100 − 400
        $this->assertEqualsWithDelta(50, $r['porcentaje_sin_iva'], 0.001);
    }

    public function test_rentabilidad_por_sucursal_se_calcula_sin_es_costo_venta(): void
    {
        $r = $this->invocar(new CalcularMetricasSucursales(), [7, 1, '2026-11-01', '2026-11-30']);

        $this->assertEqualsWithDelta(500, $r['monto_sin_iva'], 0.001);
    }

    /** SQLite trata "es_costo_venta" como texto si la columna no existe: por eso se revisa el SQL. */
    private function invocar(object $comando, array $args): array
    {
        $sql = [];
        DB::listen(function ($q) use (&$sql) { $sql[] = $q->sql; });

        $r = (new \ReflectionMethod($comando, 'calcularRentabilidadMonto'))->invoke($comando, ...$args);

        $this->assertNotEmpty($sql);
        foreach ($sql as $consulta) {
            $this->assertStringNotContainsString('es_costo_venta', $consulta);
        }

        return $r;
    }
}

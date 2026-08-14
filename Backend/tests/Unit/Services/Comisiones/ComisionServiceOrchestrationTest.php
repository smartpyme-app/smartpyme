<?php

namespace Tests\Unit\Services\Comisiones;

use App\Models\Comisiones\ComisionMovimiento;
use App\Models\Comisiones\ComisionPeriodo;
use App\Models\Comisiones\ComisionRegla;
use App\Services\Comisiones\ComisionBaseCalculator;
use App\Services\Comisiones\ComisionPeriodoService;
use App\Services\Comisiones\ComisionPorcentajeResolver;
use App\Services\Comisiones\ComisionService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use stdClass;

class ComisionServiceOrchestrationTest extends TestCase
{
    private function makeService(array $overrides = []): ComisionService
    {
        $periodo = (object) [
            'id' => 1,
            'estado' => ComisionPeriodo::ESTADO_ABIERTO,
            'fecha_fin' => '2026-07-31',
        ];

        $periodoService = new ComisionPeriodoService(
            fn () => $periodo,
            fn () => null,
            fn () => $periodo
        );

        $defaults = [
            'tieneFuncionalidad' => fn () => true,
            'obtenerConfigComisiones' => fn () => ['base_calculo' => 'subtotal_sin_iva'],
            'persistirMovimiento' => fn () => new stdClass(),
            'persistirAjuste' => fn (array $where, array $values) => (object) array_merge($where, $values),
            'obtenerConfigGiftCards' => fn () => [],
            'obtenerMovimientosVenta' => fn () => collect(),
            'obtenerVentaConDetalles' => fn () => null,
            'obtenerDevolucionesActivas' => fn () => collect(),
            'eliminarAjusteDevolucion' => fn () => null,
            'obtenerReglasActivas' => null,
            'resolver' => new ComisionPorcentajeResolver(
                fn (int $e, int $c, ?int $idRegla = null) => null,
                fn (int $e, int $s, ?int $idRegla = null) => null
            ),
        ];

        $config = array_merge($defaults, $overrides);

        return new ComisionService(
            $periodoService,
            $config['resolver'],
            $config['calculator'] ?? new ComisionBaseCalculator(),
            $config['tieneFuncionalidad'],
            $config['obtenerConfigComisiones'],
            $config['persistirMovimiento'],
            $config['persistirAjuste'],
            $config['obtenerConfigGiftCards'],
            $config['obtenerMovimientosVenta'],
            $config['obtenerVentaConDetalles'],
            $config['obtenerDevolucionesActivas'],
            $config['eliminarAjusteDevolucion'],
            null,
            null,
            null,
            $config['obtenerReglasActivas']
        );
    }

    private function movimientoVenta(array $overrides = []): object
    {
        return (object) array_merge([
            'id' => 500,
            'id_empresa' => 1,
            'id_periodo' => 1,
            'id_vendedor' => 5,
            'id_venta' => 50,
            'id_detalle_venta' => 100,
            'id_categoria' => 10,
            'id_subcategoria' => null,
            'monto_base' => 100.0,
            'porcentaje_aplicado' => 2.0,
            'monto_comision' => 2.0,
            'fecha_evento' => '2026-07-10',
        ], $overrides);
    }

    public function test_ajustar_por_anulacion_venta_aplica_ajuste_completo_por_movimiento(): void
    {
        $guardados = [];
        $movimiento = $this->movimientoVenta();

        $svc = $this->makeService([
            'obtenerMovimientosVenta' => fn () => collect([$movimiento]),
            'persistirAjuste' => function (array $where, array $values) use (&$guardados) {
                $guardados[] = compact('where', 'values');

                return (object) array_merge($where, $values);
            },
        ]);

        $fecha = Carbon::parse('2026-07-20');
        $svc->ajustarPorAnulacionVenta(50, $fecha);

        $this->assertCount(1, $guardados);
        $this->assertSame(ComisionMovimiento::ORIGEN_AJUSTE_DEVOLUCION, $guardados[0]['where']['origen']);
        $this->assertSame(500, $guardados[0]['where']['id_movimiento_origen']);
        $this->assertSame(-100.0, (float) $guardados[0]['values']['monto_base']);
        $this->assertSame(-2.0, (float) $guardados[0]['values']['monto_comision']);
        $this->assertSame($fecha->toDateTimeString(), Carbon::parse($guardados[0]['values']['fecha_evento'])->toDateTimeString());
    }

    public function test_ajustar_por_anulacion_venta_sin_movimientos_no_op(): void
    {
        $guardados = [];

        $svc = $this->makeService([
            'obtenerMovimientosVenta' => fn () => collect(),
            'persistirAjuste' => function (array $where, array $values) use (&$guardados) {
                $guardados[] = compact('where', 'values');

                return (object) array_merge($where, $values);
            },
        ]);

        $svc->ajustarPorAnulacionVenta(50);

        $this->assertSame([], $guardados);
    }

    public function test_sync_ajustes_skip_descuento_ajuste(): void
    {
        $guardados = [];
        $eliminados = 0;

        $svc = $this->makeService([
            'persistirAjuste' => function (array $where, array $values) use (&$guardados) {
                $guardados[] = compact('where', 'values');

                return (object) array_merge($where, $values);
            },
            'eliminarAjusteDevolucion' => function () use (&$eliminados) {
                $eliminados++;
            },
        ]);

        $devolucion = (object) [
            'id' => 10,
            'id_empresa' => 1,
            'id_venta' => 50,
            'tipo' => 'descuento_ajuste',
            'fecha' => '2026-07-15',
        ];

        $svc->syncAjustesPorDevolucion($devolucion);

        $this->assertSame([], $guardados);
        $this->assertSame(0, $eliminados);
    }

    public function test_sync_ajustes_funcionalidad_off_no_op(): void
    {
        $guardados = [];

        $svc = $this->makeService([
            'tieneFuncionalidad' => fn () => false,
            'obtenerMovimientosVenta' => fn () => collect([$this->movimientoVenta()]),
            'persistirAjuste' => function (array $where, array $values) use (&$guardados) {
                $guardados[] = compact('where', 'values');

                return (object) array_merge($where, $values);
            },
        ]);

        $devolucion = (object) [
            'id' => 10,
            'id_empresa' => 1,
            'id_venta' => 50,
            'tipo' => 'nota_credito',
            'fecha' => '2026-07-15',
        ];

        $svc->syncAjustesPorDevolucion($devolucion);

        $this->assertSame([], $guardados);
    }

    public function test_sync_ajustes_proporcional_acumulado(): void
    {
        $guardados = [];
        $movimiento = $this->movimientoVenta(['monto_base' => 100.0, 'monto_comision' => 2.0]);

        $detalleVenta = (object) [
            'id' => 100,
            'id_producto' => 7,
            'cantidad' => 2.0,
        ];

        $venta = (object) [
            'id' => 50,
            'detalles' => collect([$detalleVenta]),
        ];

        $detalleDevolucion = (object) [
            'id_producto' => 7,
            'gravada' => 40.0,
            'exenta' => 0.0,
            'no_sujeta' => 0.0,
        ];

        $devolucionActiva = (object) [
            'enable' => true,
            'tipo' => 'nota_credito',
            'detalles' => collect([$detalleDevolucion]),
        ];

        $svc = $this->makeService([
            'obtenerMovimientosVenta' => fn () => collect([$movimiento]),
            'obtenerVentaConDetalles' => fn () => $venta,
            'obtenerDevolucionesActivas' => fn () => collect([$devolucionActiva]),
            'persistirAjuste' => function (array $where, array $values) use (&$guardados) {
                $guardados[] = compact('where', 'values');

                return (object) array_merge($where, $values);
            },
        ]);

        $devolucion = (object) [
            'id' => 10,
            'id_empresa' => 1,
            'id_venta' => 50,
            'tipo' => 'nota_credito',
            'fecha' => Carbon::parse('2026-07-20'),
        ];

        $svc->syncAjustesPorDevolucion($devolucion);

        $this->assertCount(1, $guardados);
        $this->assertSame(-40.0, (float) $guardados[0]['values']['monto_base']);
        $this->assertSame(-0.8, (float) $guardados[0]['values']['monto_comision']);
    }

    public function test_sync_ajustes_acumula_varias_devoluciones_activas(): void
    {
        $guardados = [];
        $movimiento = $this->movimientoVenta(['monto_base' => 100.0, 'monto_comision' => 2.0]);

        $detalleVenta = (object) [
            'id' => 100,
            'id_producto' => 7,
            'cantidad' => 1.0,
        ];

        $venta = (object) [
            'id' => 50,
            'detalles' => collect([$detalleVenta]),
        ];

        $devolucion1 = (object) [
            'enable' => true,
            'tipo' => 'nota_credito',
            'detalles' => collect([(object) [
                'id_producto' => 7,
                'gravada' => 30.0,
                'exenta' => 0.0,
                'no_sujeta' => 0.0,
            ]]),
        ];

        $devolucion2 = (object) [
            'enable' => true,
            'tipo' => 'nota_credito',
            'detalles' => collect([(object) [
                'id_producto' => 7,
                'gravada' => 20.0,
                'exenta' => 0.0,
                'no_sujeta' => 0.0,
            ]]),
        ];

        $svc = $this->makeService([
            'obtenerMovimientosVenta' => fn () => collect([$movimiento]),
            'obtenerVentaConDetalles' => fn () => $venta,
            'obtenerDevolucionesActivas' => fn () => collect([$devolucion1, $devolucion2]),
            'persistirAjuste' => function (array $where, array $values) use (&$guardados) {
                $guardados[] = compact('where', 'values');

                return (object) array_merge($where, $values);
            },
        ]);

        $devolucion = (object) [
            'id' => 10,
            'id_empresa' => 1,
            'id_venta' => 50,
            'tipo' => 'nota_credito',
            'fecha' => Carbon::parse('2026-07-20'),
        ];

        $svc->syncAjustesPorDevolucion($devolucion);

        $this->assertCount(1, $guardados);
        $this->assertSame(-50.0, (float) $guardados[0]['values']['monto_base']);
        $this->assertSame(-1.0, (float) $guardados[0]['values']['monto_comision']);
    }

    public function test_sync_ajustes_sin_devoluciones_activas_elimina_ajuste(): void
    {
        $guardados = [];
        $eliminados = [];
        $movimiento = $this->movimientoVenta();

        $detalleVenta = (object) [
            'id' => 100,
            'id_producto' => 7,
            'cantidad' => 1.0,
        ];

        $venta = (object) [
            'id' => 50,
            'detalles' => collect([$detalleVenta]),
        ];

        $svc = $this->makeService([
            'obtenerMovimientosVenta' => fn () => collect([$movimiento]),
            'obtenerVentaConDetalles' => fn () => $venta,
            'obtenerDevolucionesActivas' => fn () => collect(),
            'persistirAjuste' => function (array $where, array $values) use (&$guardados) {
                $guardados[] = compact('where', 'values');

                return (object) array_merge($where, $values);
            },
            'eliminarAjusteDevolucion' => function (int $idEmpresa, int $idMovimientoOrigen) use (&$eliminados) {
                $eliminados[] = compact('idEmpresa', 'idMovimientoOrigen');
            },
        ]);

        $devolucion = (object) [
            'id' => 10,
            'id_empresa' => 1,
            'id_venta' => 50,
            'tipo' => 'nota_credito',
            'fecha' => '2026-07-20',
        ];

        $svc->syncAjustesPorDevolucion($devolucion);

        $this->assertSame([], $guardados);
        $this->assertCount(1, $eliminados);
        $this->assertSame(1, $eliminados[0]['idEmpresa']);
        $this->assertSame(500, $eliminados[0]['idMovimientoOrigen']);
    }

    public function test_sync_ajustes_enable_off_excluye_devolucion_del_acumulado(): void
    {
        $guardados = [];
        $eliminados = [];
        $movimiento = $this->movimientoVenta();

        $detalleVenta = (object) [
            'id' => 100,
            'id_producto' => 7,
            'cantidad' => 1.0,
        ];

        $venta = (object) [
            'id' => 50,
            'detalles' => collect([$detalleVenta]),
        ];

        $svc = $this->makeService([
            'obtenerMovimientosVenta' => fn () => collect([$movimiento]),
            'obtenerVentaConDetalles' => fn () => $venta,
            'obtenerDevolucionesActivas' => fn () => collect(),
            'persistirAjuste' => function (array $where, array $values) use (&$guardados) {
                $guardados[] = compact('where', 'values');

                return (object) array_merge($where, $values);
            },
            'eliminarAjusteDevolucion' => function (int $idEmpresa, int $idMovimientoOrigen) use (&$eliminados) {
                $eliminados[] = compact('idEmpresa', 'idMovimientoOrigen');
            },
        ]);

        $devolucion = (object) [
            'id' => 10,
            'id_empresa' => 1,
            'id_venta' => 50,
            'tipo' => 'nota_credito',
            'enable' => false,
            'fecha' => '2026-07-20',
        ];

        $svc->syncAjustesPorDevolucion($devolucion);

        $this->assertSame([], $guardados);
        $this->assertCount(1, $eliminados);
    }

    public function test_prorrateo_gift_evita_doble_comision(): void
    {
        $calculator = new ComisionBaseCalculator();
        $detalle = (object) [
            'gravada' => 100.0,
            'exenta' => 0.0,
            'no_sujeta' => 0.0,
        ];

        $fraccionGift = 0.4;
        $baseCompleta = $calculator->calcular($detalle, 'subtotal_sin_iva');
        $baseVenta = round($baseCompleta * (1 - $fraccionGift), 4);

        $detalleRedencion = clone $detalle;
        foreach (['gravada', 'exenta', 'no_sujeta'] as $campo) {
            $detalleRedencion->{$campo} = round((float) $detalle->{$campo} * $fraccionGift, 4);
        }
        $baseRedencion = $calculator->calcular($detalleRedencion, 'subtotal_sin_iva');

        $this->assertSame(60.0, $baseVenta);
        $this->assertSame(40.0, $baseRedencion);
        $this->assertSame(100.0, round($baseVenta + $baseRedencion, 4));
        $this->assertLessThan($baseCompleta * 2, $baseVenta + $baseRedencion);
    }

    public function test_persist_where_incluye_id_regla_con_reglas_inyectadas(): void
    {
        $guardados = [];
        $regla = (object) [
            'id' => 7,
            'alcance' => ComisionRegla::ALCANCE_GLOBAL,
            'id_vendedores' => null,
            'reemplaza_global' => false,
            'activo' => true,
            'tipo_calculo' => ComisionRegla::TIPO_POR_CATEGORIA,
            'momento_devengo' => ComisionRegla::MOMENTO_AL_PAGAR,
        ];

        $svc = $this->makeService([
            'resolver' => new ComisionPorcentajeResolver(
                fn (int $e, int $c, ?int $idRegla = null) => 2.0,
                fn (int $e, int $s, ?int $idRegla = null) => null
            ),
            'persistirMovimiento' => function (array $where, array $values) use (&$guardados) {
                $guardados[] = compact('where', 'values');

                return (object) array_merge($where, $values);
            },
            'obtenerReglasActivas' => fn () => collect([$regla]),
        ]);

        $producto = (object) ['id_categoria' => 10, 'subcategoria_id' => null];
        $detalle = (object) [
            'id' => 100,
            'gravada' => 100.0,
            'exenta' => 0.0,
            'no_sujeta' => 0.0,
            'id_vendedor' => 5,
            'producto' => $producto,
        ];
        $venta = (object) [
            'id' => 50,
            'id_empresa' => 1,
            'id_vendedor' => 5,
            'fecha_pago' => '2026-07-15',
            'detalles' => [$detalle],
        ];

        $svc->registrarVentaPagada($venta);

        $this->assertCount(1, $guardados);
        $this->assertSame(7, $guardados[0]['where']['id_regla']);
        $this->assertSame(ComisionMovimiento::ORIGEN_VENTA, $guardados[0]['where']['origen']);
        $this->assertSame(100, $guardados[0]['where']['id_detalle_venta']);

        $guardados = [];
        $svc->registrarDesdeRedencion(
            1,
            5,
            50,
            100,
            200,
            10,
            null,
            $detalle,
            Carbon::parse('2026-07-15')
        );

        $this->assertCount(1, $guardados);
        $this->assertSame(7, $guardados[0]['where']['id_regla']);
        $this->assertSame(200, $guardados[0]['where']['id_gift_card_redencion']);
        $this->assertSame(1, $guardados[0]['where']['id_empresa']);
    }

    public function test_cifras_v1_iguales_a_una_regla_por_categoria(): void
    {
        $resolver = new ComisionPorcentajeResolver(
            fn (int $e, int $c, ?int $idRegla = null) => 2.0,
            fn (int $e, int $s, ?int $idRegla = null) => null
        );
        $producto = (object) ['id_categoria' => 10, 'subcategoria_id' => null];
        $detalle = (object) [
            'id' => 100,
            'gravada' => 100.0,
            'exenta' => 0.0,
            'no_sujeta' => 0.0,
            'id_vendedor' => 5,
            'producto' => $producto,
        ];
        $venta = (object) [
            'id' => 50,
            'id_empresa' => 1,
            'id_vendedor' => 5,
            'fecha_pago' => '2026-07-15',
            'detalles' => [$detalle],
        ];
        $regla = (object) [
            'id' => 7,
            'alcance' => ComisionRegla::ALCANCE_GLOBAL,
            'id_vendedores' => null,
            'reemplaza_global' => false,
            'activo' => true,
            'tipo_calculo' => ComisionRegla::TIPO_POR_CATEGORIA,
            'momento_devengo' => ComisionRegla::MOMENTO_AL_PAGAR,
        ];

        $persistir = function (array &$guardados): callable {
            return function (array $where, array $values) use (&$guardados) {
                $guardados[] = compact('where', 'values');

                return (object) array_merge($where, $values);
            };
        };

        $v1 = [];
        $svcV1 = $this->makeService([
            'resolver' => $resolver,
            'obtenerReglasActivas' => fn () => collect(),
            'persistirMovimiento' => $persistir($v1),
        ]);
        $svcV1->registrarVentaPagada($venta);
        $svcV1->registrarDesdeRedencion(1, 5, 50, 100, 200, 10, null, $detalle, Carbon::parse('2026-07-15'));

        $conRegla = [];
        $svcRegla = $this->makeService([
            'resolver' => $resolver,
            'obtenerReglasActivas' => fn () => collect([$regla]),
            'persistirMovimiento' => $persistir($conRegla),
        ]);
        $svcRegla->registrarVentaPagada($venta);
        $svcRegla->registrarDesdeRedencion(1, 5, 50, 100, 200, 10, null, $detalle, Carbon::parse('2026-07-15'));

        $this->assertCount(2, $v1);
        $this->assertCount(2, $conRegla);
        foreach ([0, 1] as $i) {
            $this->assertSame($v1[$i]['values']['monto_base'], $conRegla[$i]['values']['monto_base']);
            $this->assertSame($v1[$i]['values']['porcentaje_aplicado'], $conRegla[$i]['values']['porcentaje_aplicado']);
            $this->assertSame($v1[$i]['values']['monto_comision'], $conRegla[$i]['values']['monto_comision']);
        }
        $this->assertSame(100.0, (float) $v1[0]['values']['monto_base']);
        $this->assertSame(2.0, (float) $v1[0]['values']['porcentaje_aplicado']);
        $this->assertSame(2.0, (float) $v1[0]['values']['monto_comision']);
    }
}

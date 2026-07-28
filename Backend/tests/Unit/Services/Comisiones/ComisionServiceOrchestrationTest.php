<?php

namespace Tests\Unit\Services\Comisiones;

use App\Models\Comisiones\ComisionMovimiento;
use App\Models\Comisiones\ComisionPeriodo;
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
        ];

        $config = array_merge($defaults, $overrides);

        return new ComisionService(
            $periodoService,
            new ComisionPorcentajeResolver(fn () => null, fn () => null),
            $config['calculator'] ?? new ComisionBaseCalculator(),
            $config['tieneFuncionalidad'],
            $config['obtenerConfigComisiones'],
            $config['persistirMovimiento'],
            $config['persistirAjuste'],
            $config['obtenerConfigGiftCards'],
            $config['obtenerMovimientosVenta'],
            $config['obtenerVentaConDetalles'],
            $config['obtenerDevolucionesActivas'],
            $config['eliminarAjusteDevolucion']
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
}

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

class ComisionServiceAjustePeriodoTest extends TestCase
{
    public function test_no_crea_movimiento_si_porcentaje_cero(): void
    {
        $creates = 0;
        $periodo = (object) ['id' => 1, 'estado' => ComisionPeriodo::ESTADO_ABIERTO];

        $periodoService = new ComisionPeriodoService(
            fn () => null,
            fn () => null,
            fn () => $periodo
        );

        $svc = new ComisionService(
            $periodoService,
            new ComisionPorcentajeResolver(fn () => null, fn () => null),
            new ComisionBaseCalculator(),
            fn () => true,
            fn () => ['base_calculo' => 'subtotal_sin_iva'],
            function () use (&$creates) {
                $creates++;

                return new stdClass();
            },
            null,
            fn () => []
        );

        $producto = (object) [
            'id_categoria' => 10,
            'subcategoria_id' => null,
        ];

        $detalle = (object) [
            'id' => 100,
            'gravada' => 50,
            'exenta' => 0,
            'no_sujeta' => 0,
            'id_vendedor' => 5,
            'producto' => $producto,
        ];

        $venta = (object) [
            'id' => 50,
            'id_empresa' => 1,
            'id_vendedor' => 3,
            'fecha_pago' => '2026-07-15',
            'detalles' => [$detalle],
        ];

        $svc->registrarVentaPagada($venta);

        $this->assertSame(0, $creates);
    }

    public function test_ajuste_anulacion_usa_monto_negativo_completo(): void
    {
        $guardados = [];
        $periodoOriginal = (object) [
            'id' => 3,
            'fecha_fin' => '2026-06-30',
            'estado' => ComisionPeriodo::ESTADO_ABIERTO,
        ];

        $periodoService = new ComisionPeriodoService(
            fn (int $id) => $id === 3 ? $periodoOriginal : null,
            fn () => $this->fail('findNextAbierto no debe llamarse'),
            fn () => $this->fail('firstOrCreate no debe llamarse')
        );

        $svc = new ComisionService(
            $periodoService,
            new ComisionPorcentajeResolver(fn () => null, fn () => null),
            new ComisionBaseCalculator(),
            fn () => true,
            fn () => ['base_calculo' => 'subtotal_sin_iva'],
            fn () => new stdClass(),
            function (array $where, array $values) use (&$guardados) {
                $guardados[] = compact('where', 'values');

                return (object) array_merge($where, $values, ['id' => 900]);
            }
        );

        $original = (object) [
            'id' => 500,
            'id_empresa' => 1,
            'id_periodo' => 3,
            'id_vendedor' => 5,
            'id_venta' => 50,
            'id_detalle_venta' => 100,
            'id_categoria' => 10,
            'id_subcategoria' => null,
            'monto_base' => 100,
            'porcentaje_aplicado' => 2,
            'monto_comision' => 2,
            'fecha_evento' => '2026-06-10',
        ];

        $result = $svc->registrarAjustePorDevolucion(
            $original,
            100,
            true,
            Carbon::parse('2026-07-01')
        );

        $this->assertNotNull($result);
        $this->assertCount(1, $guardados);
        $this->assertSame(-100.0, (float) $guardados[0]['values']['monto_base']);
        $this->assertSame(-2.0, (float) $guardados[0]['values']['monto_comision']);
        $this->assertSame(3, $guardados[0]['values']['id_periodo']);
    }

    public function test_ajuste_devolucion_actualiza_ajuste_existente(): void
    {
        $guardados = [];
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

        $ajusteExistente = new stdClass();
        $ajusteExistente->id = 901;

        $svc = new ComisionService(
            $periodoService,
            new ComisionPorcentajeResolver(fn () => null, fn () => null),
            new ComisionBaseCalculator(),
            fn () => true,
            fn () => ['base_calculo' => 'subtotal_sin_iva'],
            fn () => new stdClass(),
            function (array $where, array $values) use (&$guardados, $ajusteExistente) {
                foreach ($values as $key => $value) {
                    $ajusteExistente->{$key} = $value;
                }
                $guardados[] = $values;

                return $ajusteExistente;
            }
        );

        $original = (object) [
            'id' => 500,
            'id_empresa' => 1,
            'id_periodo' => 1,
            'id_vendedor' => 5,
            'id_venta' => 50,
            'id_detalle_venta' => 100,
            'id_categoria' => null,
            'id_subcategoria' => null,
            'monto_base' => 100,
            'porcentaje_aplicado' => 2,
            'monto_comision' => 2,
            'fecha_evento' => '2026-07-10',
        ];

        $svc->registrarAjustePorDevolucion($original, 80, false, Carbon::parse('2026-07-20'));

        $this->assertCount(1, $guardados);
        $this->assertSame(-80.0, (float) $guardados[0]['monto_base']);
        $this->assertSame(-1.6, (float) $guardados[0]['monto_comision']);
    }
}

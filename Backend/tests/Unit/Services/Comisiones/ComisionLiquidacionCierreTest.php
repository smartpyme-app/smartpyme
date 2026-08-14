<?php

namespace Tests\Unit\Services\Comisiones;

use App\Models\Comisiones\ComisionMovimiento;
use App\Models\Comisiones\ComisionPeriodo;
use App\Services\Comisiones\ComisionLiquidacionService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ComisionLiquidacionCierreTest extends TestCase
{
    public function test_salario_minimo_requiere_opt_in_explicito(): void
    {
        $movimientos = [];
        $liquidacion = null;
        $service = $this->service(20.0, false, $movimientos, $liquidacion);
        $method = new ReflectionMethod($service, 'salarioMinimoEmpresa');

        $this->assertNull($method->invoke($service, 1));
    }

    public function test_salario_minimo_usa_planilla_cuando_opt_in_esta_activo(): void
    {
        $movimientos = [];
        $liquidacion = null;
        $service = $this->service(20.0, true, $movimientos, $liquidacion);
        $method = new ReflectionMethod($service, 'salarioMinimoEmpresa');

        $this->assertSame(365.0, $method->invoke($service, 1));
    }

    public function test_flag_off_preserva_cifras_v1_sin_movimiento_salario_minimo(): void
    {
        $movimientos = [];
        $liquidacion = null;
        $service = $this->service(
            20.0,
            false,
            $movimientos,
            $liquidacion,
        );

        $this->persistirCierre($service, $this->salarioMinimo($service));

        $this->assertSame(20.0, $liquidacion['total_comision']);
        $this->assertSame(20.0, $liquidacion['total_a_pagar']);
        $this->assertSame(0.0, $liquidacion['ajuste_salario_minimo']);
        $this->assertArrayNotHasKey(ComisionMovimiento::ORIGEN_AJUSTE_SALARIO_MINIMO, $movimientos);
    }

    public function test_flag_on_completa_hasta_salario_minimo(): void
    {
        $movimientos = [];
        $liquidacion = null;
        $service = $this->service(
            20.0,
            true,
            $movimientos,
            $liquidacion,
        );

        $this->persistirCierre($service, $this->salarioMinimo($service));

        $this->assertSame(345.0, $movimientos[ComisionMovimiento::ORIGEN_AJUSTE_SALARIO_MINIMO]);
        $this->assertSame(345.0, $liquidacion['ajuste_salario_minimo']);
        $this->assertSame(365.0, $liquidacion['total_a_pagar']);
    }

    public function test_recalculo_rehace_ajuste_y_total_despues_de_bajar_comision(): void
    {
        $movimientos = [];
        $liquidacion = null;
        $service = $this->service(
            100.0,
            true,
            $movimientos,
            $liquidacion,
            (object) ['salario_base' => 0.0],
        );

        $service->recalcularParaVendedorPeriodo(1, 8, 5);

        $this->assertSame(265.0, $movimientos[ComisionMovimiento::ORIGEN_AJUSTE_SALARIO_MINIMO]);
        $this->assertSame(100.0, $liquidacion['total_comision']);
        $this->assertSame(265.0, $liquidacion['ajuste_salario_minimo']);
        $this->assertSame(365.0, $liquidacion['total_a_pagar']);
    }

    public function test_recalculo_elimina_ajuste_si_comision_supera_minimo(): void
    {
        $movimientos = [ComisionMovimiento::ORIGEN_AJUSTE_SALARIO_MINIMO => 100.0];
        $liquidacion = null;
        $service = $this->service(
            400.0,
            true,
            $movimientos,
            $liquidacion,
            (object) ['salario_base' => 0.0],
        );

        $service->recalcularParaVendedorPeriodo(1, 8, 5);

        $this->assertArrayNotHasKey(ComisionMovimiento::ORIGEN_AJUSTE_SALARIO_MINIMO, $movimientos);
        $this->assertSame(0.0, $liquidacion['ajuste_salario_minimo']);
        $this->assertSame(400.0, $liquidacion['total_a_pagar']);
    }

    private function service(
        float $totalComision,
        bool $aplicarMinimo,
        array &$movimientos,
        ?array &$liquidacion,
        ?object $existente = null,
    ): ComisionLiquidacionService {
        return new ComisionLiquidacionService(
            sumarTotalComision: fn () => $totalComision,
            sumarOrigen: fn () => 0.0,
            persistirMovimientoPeriodo: function (array $where, array $values) use (&$movimientos): void {
                $movimientos[$where['origen']] = (float) $values['monto_comision'];
            },
            eliminarMovimientoPeriodo: function (array $where) use (&$movimientos): void {
                unset($movimientos[$where['origen']]);
            },
            persistirLiquidacion: function (array $where, array $values) use (&$liquidacion): void {
                $liquidacion = array_merge($where, $values);
            },
            obtenerPeriodoRecalculo: fn () => (object) [
                'id' => 8,
                'estado' => ComisionPeriodo::ESTADO_CERRADO,
                'fecha_fin' => '2026-07-31',
            ],
            obtenerLiquidacion: fn () => $existente,
            obtenerConfigComisiones: fn () => ['aplicar_salario_minimo' => $aplicarMinimo],
            obtenerConfigPlanilla: fn () => (object) ['configuracion' => ['salario_minimo' => 365]],
        );
    }

    private function persistirCierre(ComisionLiquidacionService $service, ?float $minimo): void
    {
        $method = new ReflectionMethod($service, 'persistirCierreVendedor');
        $method->invoke(
            $service,
            1,
            (object) ['id' => 8, 'fecha_fin' => '2026-07-31'],
            5,
            [],
            '2026-07-01',
            '2026-07-31',
            $minimo,
        );
    }

    private function salarioMinimo(ComisionLiquidacionService $service): ?float
    {
        return (new ReflectionMethod($service, 'salarioMinimoEmpresa'))->invoke($service, 1);
    }
}

<?php

namespace Tests\Unit\Contabilidad;

use App\Services\Contabilidad\BalanceGeneralNiifSvPresenter;
use App\Services\Contabilidad\EstadoResultadosNiifSvPresenter;
use App\Services\Contabilidad\FlujoEfectivoHibridoNiifSvPresenter;
use Carbon\Carbon;
use Mockery;
use PHPUnit\Framework\TestCase;

class FlujoEfectivoHibridoNiifSvPresenterTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_opening_snapshot_range_mid_month(): void
    {
        $fi = Carbon::parse('2025-06-05')->startOfDay();
        [$openStart, $openEnd] = FlujoEfectivoHibridoNiifSvPresenter::openingSnapshotRange($fi);

        $this->assertSame('2025-06-01', $openStart->toDateString());
        $this->assertSame('2025-06-04', $openEnd->toDateString());
    }

    public function test_opening_snapshot_range_first_of_month(): void
    {
        $fi = Carbon::parse('2025-06-01')->startOfDay();
        [$openStart, $openEnd] = FlujoEfectivoHibridoNiifSvPresenter::openingSnapshotRange($fi);

        $this->assertSame('2025-05-01', $openStart->toDateString());
        $this->assertSame('2025-05-31', $openEnd->toDateString());
    }

    public function test_compute_one_period_aggregates_utilidad_deprec_and_wc(): void
    {
        $balance = Mockery::mock(BalanceGeneralNiifSvPresenter::class);
        $er = Mockery::mock(EstadoResultadosNiifSvPresenter::class);

        $empty = static fn (): array => array_fill_keys([
            'efectivo_equivalentes', 'cuentas_cobrar_clientes', 'documentos_cobrar', 'provision_incobrables',
            'inventarios', 'iva_credito_fiscal', 'pago_cuenta_acumulado', 'gastos_anticipados', 'otros_activos_corrientes',
            'propiedad_planta_equipo', 'depreciacion_acumulada', 'activos_intangibles', 'inversiones_largo_plazo',
            'activos_impuesto_diferido', 'otros_activos_no_corrientes', 'cuentas_pagar_proveedores', 'prestamos_corto_plazo',
            'iva_debito_fiscal', 'isr_por_pagar', 'afp_por_pagar', 'isss_por_pagar', 'retenciones_isr_empleados',
            'otras_cuentas_pagar_corrientes', 'prestamos_largo_plazo', 'provision_indemnizaciones', 'pasivos_impuesto_diferido',
            'otros_pasivos_no_corrientes', 'capital_social', 'reserva_legal', 'utilidades_retenidas', 'utilidad_ejercicio',
            'superavit_revaluacion',
        ], 0.0);

        $L0 = $empty();
        $L1 = $empty();
        $L0['cuentas_cobrar_clientes'] = 100.0;
        $L1['cuentas_cobrar_clientes'] = 130.0;
        $L0['cuentas_pagar_proveedores'] = 50.0;
        $L1['cuentas_pagar_proveedores'] = 40.0;
        $L0['efectivo_equivalentes'] = 200.0;
        $L1['efectivo_equivalentes'] = 215.0;

        $balance->shouldReceive('rawLines')
            ->once()
            ->andReturn(['lines' => $L0, 'utilidad_ejercicio_computada' => 0.0]);
        $balance->shouldReceive('rawLines')
            ->once()
            ->andReturn(['lines' => $L1, 'utilidad_ejercicio_computada' => 0.0]);

        $er->shouldReceive('build')
            ->once()
            ->andReturn([
                'cascada' => [EstadoResultadosNiifSvPresenter::LBL_UTIL_NETA => 10.0],
                'L' => ['gasto_venta_deprec' => 5.0, 'gasto_admin_deprec' => 3.0],
            ]);

        $svc = new FlujoEfectivoHibridoNiifSvPresenter($balance, $er);
        $ref = new \ReflectionClass($svc);
        $m = $ref->getMethod('computeOnePeriod');
        $m->setAccessible(true);

        $fi = Carbon::parse('2025-06-05')->startOfDay();
        $ff = Carbon::parse('2025-06-30')->endOfDay();
        $out = $m->invoke($svc, 1, $fi, $ff);

        $this->assertEqualsWithDelta(-22.0, $out['operacion']['total'], 0.001,
            'Utilidad 10 + deprec 8 + WC (AR -30 + AP -10) = -22');
        $this->assertEqualsWithDelta(15.0, $out['efectivo']['variacion_linea'], 0.001);
        $this->assertEqualsWithDelta(-37.0, $out['conciliacion']['diferencia'], 0.001);
    }
}

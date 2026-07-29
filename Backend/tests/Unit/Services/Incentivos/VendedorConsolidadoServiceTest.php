<?php

namespace Tests\Unit\Services\Incentivos;

use App\Models\Bonos\BonoGenerado;
use App\Models\Comisiones\ComisionMovimiento;
use App\Services\Bonos\BonoMetaCalculator;
use App\Services\Bonos\BonoReglaEvaluator;
use App\Services\Incentivos\VendedorConsolidadoService;
use Mockery;
use Tests\TestCase;

class VendedorConsolidadoServiceTest extends TestCase
{
    private const VENDEDOR_AISLADO = 987654321;

    protected function tearDown(): void
    {
        ComisionMovimiento::withoutGlobalScope('empresa')
            ->where('id_vendedor', self::VENDEDOR_AISLADO)
            ->delete();
        BonoGenerado::withoutGlobalScope('empresa')
            ->where('id_vendedor', self::VENDEDOR_AISLADO)
            ->delete();
        Mockery::close();
        parent::tearDown();
    }

    public function test_total_a_pagar_siempre_desglosado_y_omite_comisiones_sin_flag_ni_historial(): void
    {
        $service = new VendedorConsolidadoService(
            Mockery::mock(BonoMetaCalculator::class),
            Mockery::mock(BonoReglaEvaluator::class),
            fn (int $idEmpresa, string $slug) => $slug === 'bonos-vendedores',
        );

        $result = $service->consolidado(1, self::VENDEDOR_AISLADO, '2026-12-01', '2026-12-31');

        $this->assertArrayNotHasKey('comisiones', $result);
        $this->assertSame([
            'comisiones' => 0.0,
            'bonos_aprobados_o_pagados' => 0.0,
            'desglose' => true,
        ], $result['total_a_pagar']);
    }

    public function test_incluye_comisiones_historicas_aunque_flag_off(): void
    {
        ComisionMovimiento::withoutGlobalScope('empresa')->create([
            'id_empresa' => 1,
            'id_vendedor' => self::VENDEDOR_AISLADO,
            'id_periodo' => null,
            'origen' => ComisionMovimiento::ORIGEN_VENTA,
            'monto_base' => 100,
            'porcentaje_aplicado' => 10,
            'monto_comision' => 10,
            'fecha_evento' => '2026-01-15',
        ]);

        $service = new VendedorConsolidadoService(
            Mockery::mock(BonoMetaCalculator::class),
            Mockery::mock(BonoReglaEvaluator::class),
            fn () => false,
        );

        $result = $service->consolidado(1, self::VENDEDOR_AISLADO, '2026-01-01', '2026-01-31');

        $this->assertArrayHasKey('comisiones', $result);
        $this->assertSame(10.0, $result['comisiones']['total']);
        $this->assertSame(10.0, $result['total_a_pagar']['comisiones']);
    }

    public function test_suma_bonos_aprobados_en_total_a_pagar(): void
    {
        BonoGenerado::withoutGlobalScope('empresa')->create([
            'id_empresa' => 1,
            'id_vendedor' => self::VENDEDOR_AISLADO,
            'id_regla' => 1,
            'periodo_inicio' => '2026-03-01',
            'periodo_fin' => '2026-03-31',
            'monto_ventas_base' => 5000,
            'monto' => 100,
            'estado' => BonoGenerado::ESTADO_APROBADO,
        ]);

        $service = new VendedorConsolidadoService(
            Mockery::mock(BonoMetaCalculator::class),
            Mockery::mock(BonoReglaEvaluator::class),
            fn () => true,
        );

        $result = $service->consolidado(1, self::VENDEDOR_AISLADO, '2026-03-01', '2026-03-31');

        $this->assertSame(100.0, $result['total_a_pagar']['bonos_aprobados_o_pagados']);
        $this->assertCount(1, $result['bonos']);
    }
}

<?php

namespace Tests\Unit\Services\Comisiones;

use App\Models\Comisiones\ComisionPeriodo;
use App\Models\Comisiones\ComisionRegla;
use App\Services\Comisiones\Calculators\ComisionCalculatorFactory;
use App\Services\Comisiones\ComisionLiquidacionService;
use App\Services\Comisiones\ComisionPorcentajeResolver;
use App\Services\Comisiones\ComisionReglaScope;
use App\Services\Comisiones\ComisionVentasPeriodo;
use PHPUnit\Framework\TestCase;

class ComisionPreviewVolumenTest extends TestCase
{
    public function test_preview_volumen_calcula_sin_persistir(): void
    {
        $periodo = $this->periodo(ComisionPeriodo::ESTADO_ABIERTO);
        $ventas = $this->createMock(ComisionVentasPeriodo::class);
        $ventas->method('total')->willReturn(1500.0);

        $svc = new ComisionLiquidacionService(
            new ComisionCalculatorFactory(new ComisionPorcentajeResolver(
                fn () => null,
                fn () => null
            )),
            new ComisionReglaScope(),
            $ventas,
            fn () => $periodo,
            fn () => [$this->reglaVolumen()],
            fn () => [5],
        );

        $out = $svc->previewVolumen(1, 8);

        $this->assertCount(1, $out);
        $this->assertSame(5, $out[0]['id_vendedor']);
        $this->assertSame(7, $out[0]['id_regla']);
        $this->assertSame(30.0, $out[0]['monto']);
        $this->assertSame(1500.0, $out[0]['monto_base']);
        $this->assertSame(2.0, $out[0]['porcentaje']);
    }

    public function test_preview_volumen_vacio_si_periodo_cerrado(): void
    {
        $periodo = $this->periodo(ComisionPeriodo::ESTADO_CERRADO);
        $ventas = $this->createMock(ComisionVentasPeriodo::class);
        $ventas->expects($this->never())->method('total');

        $svc = new ComisionLiquidacionService(
            new ComisionCalculatorFactory(new ComisionPorcentajeResolver(
                fn () => null,
                fn () => null
            )),
            new ComisionReglaScope(),
            $ventas,
            fn () => $periodo,
            fn () => [$this->reglaVolumen()],
            fn () => [5],
        );

        $this->assertSame([], $svc->previewVolumen(1, 8));
    }

    private function periodo(string $estado): object
    {
        return (object) [
            'id' => 8,
            'id_empresa' => 1,
            'fecha_inicio' => '2026-07-01',
            'fecha_fin' => '2026-07-31',
            'estado' => $estado,
        ];
    }

    private function reglaVolumen(): object
    {
        return (object) [
            'id' => 7,
            'tipo_calculo' => ComisionRegla::TIPO_POR_VOLUMEN,
            'alcance' => ComisionRegla::ALCANCE_GLOBAL,
            'reemplaza_global' => false,
            'id_vendedores' => null,
            'config' => [
                'tramos' => [
                    ['umbral' => 0, 'porcentaje' => 1],
                    ['umbral' => 1000, 'porcentaje' => 2],
                ],
            ],
        ];
    }
}

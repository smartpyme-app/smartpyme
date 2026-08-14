<?php

namespace Tests\Unit\Services\Comisiones;

use App\Models\Comisiones\ComisionMovimiento;
use App\Services\Comisiones\ComisionReporteService;
use App\Services\Comisiones\ComisionSalarioMinimo;
use PHPUnit\Framework\TestCase;

class ComisionSalarioMinimoTest extends TestCase
{
    public function test_sin_minimo_no_ajusta(): void
    {
        $this->assertSame(0.0, ComisionSalarioMinimo::ajuste(100.0, null));
    }

    public function test_complementa_hasta_el_minimo(): void
    {
        $this->assertSame(265.0, ComisionSalarioMinimo::ajuste(100.0, 365.0));
    }

    public function test_no_ajusta_si_ya_cubre_el_minimo(): void
    {
        $this->assertSame(0.0, ComisionSalarioMinimo::ajuste(400.0, 365.0));
        $this->assertSame(0.0, ComisionSalarioMinimo::ajuste(365.0, 365.0));
    }

    public function test_minimo_prioriza_configuraciones_generales(): void
    {
        $config = $this->stubPlanilla(
            ['salario_minimo' => 365],
            ['salario_minimo' => 100]
        );
        $this->assertSame(365.0, ComisionSalarioMinimo::minimoDePlanilla($config));
    }

    public function test_minimo_cae_a_top_level_si_generales_no_lo_tienen(): void
    {
        $config = $this->stubPlanilla(['moneda' => 'USD'], ['salario_minimo' => 2992.38]);
        $this->assertSame(2992.38, ComisionSalarioMinimo::minimoDePlanilla($config));
    }

    public function test_minimo_null_si_no_hay_config_ni_clave(): void
    {
        $this->assertNull(ComisionSalarioMinimo::minimoDePlanilla(null));
        $this->assertNull(ComisionSalarioMinimo::minimoDePlanilla($this->stubPlanilla(['moneda' => 'USD'], [])));
    }

    /**
     * @param  array<string, mixed>  $generales
     * @param  array<string, mixed>|null  $topLevel
     */
    private function stubPlanilla(array $generales, ?array $topLevel): object
    {
        return new class($generales, $topLevel) {
            public ?array $configuracion;

            /** @param array<string, mixed> $generales */
            public function __construct(private array $generales, ?array $topLevel)
            {
                $this->configuracion = $topLevel;
            }

            /** @return array<string, mixed> */
            public function getConfiguracionesGenerales(): array
            {
                return $this->generales;
            }
        };
    }

    public function test_etiquetas_origen_periodo(): void
    {
        $this->assertSame('ajuste_periodo', ComisionMovimiento::ORIGEN_AJUSTE_PERIODO);
        $this->assertSame('salario_base', ComisionMovimiento::ORIGEN_SALARIO_BASE);
        $this->assertSame('ajuste_salario_minimo', ComisionMovimiento::ORIGEN_AJUSTE_SALARIO_MINIMO);
        $this->assertNotSame(
            ComisionMovimiento::ORIGEN_AJUSTE_PERIODO,
            ComisionReporteService::etiquetaOrigen(ComisionMovimiento::ORIGEN_AJUSTE_PERIODO)
        );
        $this->assertNotSame(
            ComisionMovimiento::ORIGEN_SALARIO_BASE,
            ComisionReporteService::etiquetaOrigen(ComisionMovimiento::ORIGEN_SALARIO_BASE)
        );
        $this->assertNotSame(
            ComisionMovimiento::ORIGEN_AJUSTE_SALARIO_MINIMO,
            ComisionReporteService::etiquetaOrigen(ComisionMovimiento::ORIGEN_AJUSTE_SALARIO_MINIMO)
        );
    }
}

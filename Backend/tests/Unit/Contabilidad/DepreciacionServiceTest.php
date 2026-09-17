<?php

namespace Tests\Unit\Contabilidad;

use App\Models\Contabilidad\Activo;
use App\Services\Contabilidad\DepreciacionService;
use PHPUnit\Framework\TestCase;

class DepreciacionServiceTest extends TestCase
{
    private DepreciacionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DepreciacionService;
    }

    public function test_calcular_cuota_mensual_linea_recta(): void
    {
        $activo = new Activo([
            'valor_compra' => 1200,
            'valor_residual' => 0,
            'vida_util' => 3,
            'es_usado' => false,
        ]);

        $this->assertSame(33.33, $this->service->calcularCuotaMensual($activo));
    }

    public function test_ajustar_por_bien_usado(): void
    {
        $activo = new Activo([
            'valor_compra' => 1000,
            'es_usado' => true,
            'porcentaje_base_usado' => 50,
        ]);

        $this->assertSame(500.0, $this->service->ajustarPorBienUsado($activo));
    }

    public function test_vida_util_desde_activo(): void
    {
        $activo = new Activo(['vida_util' => 5]);

        $this->assertSame(5.0, $this->service->vidaUtilAnios($activo));
    }
}

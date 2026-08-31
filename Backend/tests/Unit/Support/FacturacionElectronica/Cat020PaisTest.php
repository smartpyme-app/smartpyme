<?php

namespace Tests\Unit\Support\FacturacionElectronica;

use App\Support\FacturacionElectronica\Cat020Pais;
use PHPUnit\Framework\TestCase;

class Cat020PaisTest extends TestCase
{
    public function test_codigo_obsoleto_9411_costa_rica_resuelve_a_iso_cr(): void
    {
        $this->assertSame(
            ['cod' => 'CR', 'nombre' => 'Costa Rica'],
            Cat020Pais::resolver('9411', 'COSTA RICA')
        );
        $this->assertSame(
            ['cod' => 'CR', 'nombre' => 'Costa Rica'],
            Cat020Pais::resolver('9411', null)
        );
    }

    public function test_iso_vigente_conserva_codigo_y_usa_nombre_oficial(): void
    {
        $this->assertSame(
            ['cod' => 'CR', 'nombre' => 'Costa Rica'],
            Cat020Pais::resolver('CR', 'Ketsali')
        );
    }

    public function test_guatemala_y_eeuu_viejos_resuelven_a_iso(): void
    {
        $this->assertSame(
            ['cod' => 'GT', 'nombre' => 'Guatemala'],
            Cat020Pais::resolver('9483', 'GUATEMALA')
        );
        $this->assertSame(
            ['cod' => 'US', 'nombre' => 'Estados Unidos'],
            Cat020Pais::resolver('9450', 'EE UU')
        );
    }
}

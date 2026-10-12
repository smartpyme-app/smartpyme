<?php

namespace Tests\Unit\Services\Contadores;

use App\Services\Contadores\ContadorCarteraMetricasService;
use PHPUnit\Framework\TestCase;

class ContadorCarteraMetricasServiceTest extends TestCase
{
    public function test_estado_desde_avance_usa_mismos_umbrales_que_portal(): void
    {
        $this->assertSame('lista', ContadorCarteraMetricasService::estadoDesdeAvance(95));
        $this->assertSame('lista', ContadorCarteraMetricasService::estadoDesdeAvance(100));
        $this->assertSame('casi', ContadorCarteraMetricasService::estadoDesdeAvance(85));
        $this->assertSame('proceso', ContadorCarteraMetricasService::estadoDesdeAvance(50));
        $this->assertSame('atrasada', ContadorCarteraMetricasService::estadoDesdeAvance(44));
    }
}

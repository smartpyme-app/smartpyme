<?php

namespace Tests\Unit\Contabilidad;

use App\Services\Contabilidad\DepreciacionService;
use PHPUnit\Framework\TestCase;

class DepreciacionServiceSaldoInicialTest extends TestCase
{
    public function test_meses_omitidos_con_saldo_inicial(): void
    {
        $this->assertSame(0, DepreciacionService::mesesOmitidosPorSaldoInicial(0, 100, 48));
        $this->assertSame(3, DepreciacionService::mesesOmitidosPorSaldoInicial(300, 100, 48));
        $this->assertSame(47, DepreciacionService::mesesOmitidosPorSaldoInicial(5000, 100, 48));
    }
}

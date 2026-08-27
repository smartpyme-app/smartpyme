<?php

namespace Tests\Unit\Services\CreditosClientes;

use App\Services\CreditosClientes\CorrelativoVenta;
use PHPUnit\Framework\TestCase;

class CorrelativoVentaTest extends TestCase
{
    public function test_asigna_si_no_hay_correlativo(): void
    {
        $this->assertTrue(CorrelativoVenta::debeAsignar(null));
        $this->assertTrue(CorrelativoVenta::debeAsignar(''));
        $this->assertTrue(CorrelativoVenta::debeAsignar(0));
        $this->assertFalse(CorrelativoVenta::debeAsignar(19));
        $this->assertFalse(CorrelativoVenta::debeAsignar('19'));
    }
}

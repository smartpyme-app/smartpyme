<?php

namespace Tests\Unit\Services\CreditosClientes;

use App\Services\CreditosClientes\CupoCredito;
use PHPUnit\Framework\TestCase;

class CupoCreditoTest extends TestCase
{
    public function test_cabe_si_no_hay_limite(): void
    {
        $this->assertTrue(CupoCredito::cabe(null, 50, 100));
        $this->assertTrue(CupoCredito::cabe(0, 50, 100));
    }

    public function test_cabe_si_saldo_mas_monto_no_supera_limite(): void
    {
        $this->assertTrue(CupoCredito::cabe(200.00, 100.00, 100.00));
    }

    public function test_no_cabe_si_supera_el_limite(): void
    {
        $this->assertFalse(CupoCredito::cabe(200.00, 150.00, 60.00));
    }
}

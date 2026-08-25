<?php

namespace Tests\Unit\Services\CreditosClientes;

use App\Services\CreditosClientes\CuotaInicialFactura;
use PHPUnit\Framework\TestCase;

class CuotaInicialFacturaTest extends TestCase
{
    public function test_el_total_de_la_venta_es_la_primera_cuota_no_el_contrato(): void
    {
        $this->assertTrue(CuotaInicialFactura::coincide(33.33, 100.00, 3));
        $this->assertFalse(CuotaInicialFactura::coincide(100.00, 100.00, 3));
    }
}

<?php

namespace Tests\Unit\Services\CreditosClientes;

use App\Services\CreditosClientes\KardexCredito;
use PHPUnit\Framework\TestCase;

class KardexCreditoTest extends TestCase
{
    public function test_bien_cuota_1_mueve_inventario(): void
    {
        $this->assertTrue(KardexCredito::debeMoverInventario('bien', 1));
    }

    public function test_bien_cuota_2_no_mueve_inventario(): void
    {
        $this->assertFalse(KardexCredito::debeMoverInventario('bien', 2));
    }

    public function test_servicio_nunca_mueve_inventario(): void
    {
        $this->assertFalse(KardexCredito::debeMoverInventario('servicio', 1));
    }

    public function test_prestamo_nunca_mueve_inventario(): void
    {
        $this->assertFalse(KardexCredito::debeMoverInventario('prestamo', 1));
    }
}

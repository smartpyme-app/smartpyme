<?php

namespace Tests\Unit\Planilla;

use App\Models\Planilla\ComisionEmpleado;
use PHPUnit\Framework\TestCase;

class ComisionEmpleadoTest extends TestCase
{
    public function test_calcula_monto_comision_porcentaje(): void
    {
        $this->assertSame(10.0, ComisionEmpleado::calcularMonto(100, 10));
        $this->assertSame(25.5, ComisionEmpleado::calcularMonto(170, 15));
    }

    public function test_redondea_monto_a_dos_decimales(): void
    {
        $this->assertSame(5.0, ComisionEmpleado::calcularMonto(33.33, 15));
        $this->assertSame(3.33, ComisionEmpleado::calcularMonto(33.33, 10));
    }

    public function test_origenes_soportados(): void
    {
        $this->assertContains('venta', ComisionEmpleado::ORIGENES);
        $this->assertContains('manual', ComisionEmpleado::ORIGENES);
        $this->assertContains('canje_tarjeta', ComisionEmpleado::ORIGENES);
    }
}

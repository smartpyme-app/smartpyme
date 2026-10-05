<?php

namespace Tests\Unit\Services\Contabilidad;

use PHPUnit\Framework\TestCase;

class LibroIvaResumenElSalvadorFormulasTest extends TestCase
{
    public function test_iva_a_pagar_resta_retenciones_sin_remanente(): void
    {
        $debito = 130.0;
        $credito = 80.0;
        $retPerc = 10.0;
        $ivaAPagar = round($debito - $credito - $retPerc, 2);

        $this->assertSame(40.0, $ivaAPagar);
    }

    public function test_pago_a_cuenta_isr_mas_renta_retenida(): void
    {
        $ingresosBrutos = 8000.0;
        $pagoCuenta = round($ingresosBrutos * 0.0175, 2);
        $rentaRetenida = 25.5;
        $total = round($pagoCuenta + $rentaRetenida, 2);

        $this->assertSame(140.0, $pagoCuenta);
        $this->assertSame(165.5, $total);
    }
}

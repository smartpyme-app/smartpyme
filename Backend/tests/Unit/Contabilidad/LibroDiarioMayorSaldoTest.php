<?php

namespace Tests\Unit\Contabilidad;

use PHPUnit\Framework\TestCase;

/**
 * ponytail: self-check del saldo corrido del mayor (misma regla que construirLibroDiarioMayor).
 */
class LibroDiarioMayorSaldoTest extends TestCase
{
    public function test_saldo_corrido_deudor_parte_de_saldo_anterior(): void
    {
        $saldo = 100.0;
        foreach ([['debe' => 50, 'haber' => 0], ['debe' => 0, 'haber' => 20]] as $mov) {
            $saldo += (float) $mov['debe'] - (float) $mov['haber'];
        }

        $this->assertSame(130.0, $saldo);
    }

    public function test_saldo_corrido_acreedor_parte_de_saldo_anterior(): void
    {
        $saldo = 200.0;
        foreach ([['debe' => 0, 'haber' => 80], ['debe' => 30, 'haber' => 0]] as $mov) {
            $saldo += (float) $mov['haber'] - (float) $mov['debe'];
        }

        $this->assertSame(250.0, $saldo);
    }
}

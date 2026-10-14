<?php

namespace Tests\Unit\Services\Contabilidad;

use App\Services\Contabilidad\LibroIvaVentasPeriodo;
use PHPUnit\Framework\TestCase;

class LibroIvaVentasPeriodoTest extends TestCase
{
    public function test_columnas_de_venta_no_incluyen_dte(): void
    {
        $cols = LibroIvaVentasPeriodo::columnasSinDte();

        $this->assertContains('ventas.id', $cols);
        $this->assertContains('ventas.gravada', $cols);
        $this->assertContains('ventas.iva', $cols);
        $this->assertNotContains('ventas.dte', $cols);
        $this->assertNotContains('ventas.dte_s3_key', $cols);
    }
}

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

    public function test_columnas_devolucion_no_incluyen_iva_percibido(): void
    {
        $cols = LibroIvaVentasPeriodo::columnasDevolucionSinDte();

        $this->assertNotContains('devoluciones_venta.iva_percibido', $cols);
        $this->assertContains('devoluciones_venta.iva_retenido', $cols);
    }
}

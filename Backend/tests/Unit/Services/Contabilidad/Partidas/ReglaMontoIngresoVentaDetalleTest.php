<?php

namespace Tests\Unit\Services\Contabilidad\Partidas;

use App\Services\Contabilidad\Partidas\ReglaMontoIngresoVentaDetalle;
use PHPUnit\Framework\TestCase;

class ReglaMontoIngresoVentaDetalleTest extends TestCase
{
    public function test_usa_gravada_exenta_no_sujeta(): void
    {
        $detalle = (object) [
            'gravada' => 750.0,
            'exenta' => 0,
            'no_sujeta' => 0,
            'total' => 847.5,
            'iva' => 97.5,
        ];

        $this->assertSame(750.0, ReglaMontoIngresoVentaDetalle::haber($detalle));
    }

    public function test_total_con_iva_y_sin_bases_usa_total_menos_iva(): void
    {
        $detalle = (object) ['total' => 847.5, 'iva' => 97.5];

        $this->assertSame(750.0, ReglaMontoIngresoVentaDetalle::haber($detalle));
    }

    public function test_total_neto_sin_gravada_no_resta_iva(): void
    {
        $detalle = (object) [
            'total' => 750.0,
            'total_iva' => 847.5,
            'iva' => 97.5,
            'gravada' => 0.0,
        ];

        $this->assertSame(750.0, ReglaMontoIngresoVentaDetalle::haber($detalle));
    }
}

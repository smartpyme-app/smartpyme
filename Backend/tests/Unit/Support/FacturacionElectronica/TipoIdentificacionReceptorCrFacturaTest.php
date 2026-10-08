<?php

namespace Tests\Unit\Support\FacturacionElectronica;

use App\Support\FacturacionElectronica\TipoIdentificacionReceptor;
use PHPUnit\Framework\TestCase;

final class TipoIdentificacionReceptorCrFacturaTest extends TestCase
{
    public function test_factura_rechaza_tipo_06_y_placeholders(): void
    {
        self::assertFalse(TipoIdentificacionReceptor::estructuraValidaCostaRicaFactura('06', '00000000000000'));
        self::assertFalse(TipoIdentificacionReceptor::estructuraValidaCostaRicaFactura('05', '00000000000000000000'));
    }

    public function test_factura_acepta_longitudes_oficiales(): void
    {
        self::assertTrue(TipoIdentificacionReceptor::estructuraValidaCostaRicaFactura('01', '123456789'));
        self::assertTrue(TipoIdentificacionReceptor::estructuraValidaCostaRicaFactura('02', '3101234567'));
        self::assertTrue(TipoIdentificacionReceptor::estructuraValidaCostaRicaFactura('03', '12345678901'));
    }
}

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

    public function test_cedula_juridica_acepta_letras_de_10_caracteres(): void
    {
        self::assertTrue(TipoIdentificacionReceptor::estructuraValidaCostaRicaFactura('02', '3101A00001'));
        self::assertSame('3101A00001', TipoIdentificacionReceptor::normalizarNumeroCostaRica('02', '3-101-A00001'));
        self::assertFalse(TipoIdentificacionReceptor::estructuraValidaCostaRicaFactura('02', '3101A0000'));
    }

    public function test_juridica_3101AB0001_es_tipo_02_y_conserva_letras(): void
    {
        self::assertSame('02', TipoIdentificacionReceptor::costaRica(null, '3101AB0001', ''));
        self::assertSame('3101AB0001', TipoIdentificacionReceptor::numeroCostaRica('02', '3101AB0001', ''));
        self::assertSame('02', TipoIdentificacionReceptor::costaRica(null, '', '3101AB0001'));
        self::assertSame('3101AB0001', TipoIdentificacionReceptor::numeroCostaRica('02', '', '3101AB0001'));
    }

    public function test_cedula_fisica_sigue_siendo_9_digitos(): void
    {
        self::assertTrue(TipoIdentificacionReceptor::estructuraValidaCostaRicaFactura('01', '123456789'));
        self::assertFalse(TipoIdentificacionReceptor::estructuraValidaCostaRicaFactura('01', '12345678A'));
    }
}

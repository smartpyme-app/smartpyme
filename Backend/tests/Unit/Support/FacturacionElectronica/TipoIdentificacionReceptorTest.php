<?php

namespace Tests\Unit\Support\FacturacionElectronica;

use App\Support\FacturacionElectronica\TipoIdentificacionReceptor;
use PHPUnit\Framework\TestCase;

/**
 * Si no hay tipo guardado, FE CR/SV debe inferir igual que hoy.
 */
class TipoIdentificacionReceptorTest extends TestCase
{
    public function test_cr_sin_tipo_nit_largo_es_cedula_juridica(): void
    {
        $this->assertSame('02', TipoIdentificacionReceptor::costaRica(null, '3102925240', ''));
        $this->assertSame('02', TipoIdentificacionReceptor::costaRica('', '3102925240', '123456789'));
    }

    public function test_cr_sin_tipo_solo_dui_largo_es_cedula_fisica(): void
    {
        $this->assertSame('01', TipoIdentificacionReceptor::costaRica(null, '', '123456789'));
        $this->assertSame('01', TipoIdentificacionReceptor::costaRica('  ', '12', '123456789'));
    }

    public function test_cr_sin_tipo_ni_numeros_validos_es_null(): void
    {
        $this->assertNull(TipoIdentificacionReceptor::costaRica(null, '', ''));
        $this->assertNull(TipoIdentificacionReceptor::costaRica('', '123', '456'));
    }

    public function test_cr_con_tipo_guardado_no_infiere(): void
    {
        $this->assertSame('03', TipoIdentificacionReceptor::costaRica('03', '3102925240', '123456789'));
        $this->assertSame('05', TipoIdentificacionReceptor::costaRica('05', '', ''));
    }

    public function test_cr_tipo_invalido_cae_a_inferencia(): void
    {
        $this->assertSame('02', TipoIdentificacionReceptor::costaRica('99', '3102925240', ''));
        $this->assertNull(TipoIdentificacionReceptor::costaRica('dni', '', ''));
    }

    public function test_sv_sin_tipo_nit_luego_dui_dui_gana(): void
    {
        $this->assertSame('36', TipoIdentificacionReceptor::elSalvador(null, '0614-123456-001-0', null));
        $this->assertSame('13', TipoIdentificacionReceptor::elSalvador('', null, '01234567-8'));
        $this->assertSame('13', TipoIdentificacionReceptor::elSalvador(null, '0614-123456-001-0', '01234567-8'));
        $this->assertNull(TipoIdentificacionReceptor::elSalvador(null, null, null));
        $this->assertNull(TipoIdentificacionReceptor::elSalvador('', '', ''));
    }

    public function test_sv_con_tipo_guardado_no_infiere(): void
    {
        $this->assertSame('03', TipoIdentificacionReceptor::elSalvador('03', '0614-123456-001-0', '01234567-8'));
        $this->assertSame('36', TipoIdentificacionReceptor::elSalvador('36', null, '01234567-8'));
    }

    public function test_sv_numero_con_tipo_36_usa_nit(): void
    {
        $this->assertSame('06141234560010', TipoIdentificacionReceptor::numeroElSalvador('36', '0614-123456-001-0', '01234567-8'));
    }

    public function test_sv_numero_con_tipo_13_usa_dui(): void
    {
        $this->assertSame('01234567-8', TipoIdentificacionReceptor::numeroElSalvador('13', '0614-123456-001-0', '01234567-8'));
    }

    public function test_cr_numero_tipo_01_rellena_9_digitos(): void
    {
        $this->assertSame('123456789', TipoIdentificacionReceptor::numeroCostaRica('01', '', '123456789'));
    }

    public function test_cr_numero_tipo_03_no_recorta_dimex(): void
    {
        $this->assertSame('123456789012', TipoIdentificacionReceptor::numeroCostaRica('03', '', '123456789012'));
    }
}

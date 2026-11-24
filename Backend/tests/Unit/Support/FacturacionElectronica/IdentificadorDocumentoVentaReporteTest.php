<?php

namespace Tests\Unit\Support\FacturacionElectronica;

use App\Models\Admin\Empresa;
use App\Support\FacturacionElectronica\IdentificadorDocumentoVentaReporte;
use PHPUnit\Framework\TestCase;

final class IdentificadorDocumentoVentaReporteTest extends TestCase
{
    public function test_cr_usa_numero_control_formateado_o_correlativo(): void
    {
        $empresa = new Empresa(['pais' => 'Costa Rica', 'cod_pais' => 'CR']);

        self::assertSame('Consecutivo', IdentificadorDocumentoVentaReporte::etiqueta($empresa));
        self::assertSame(
            '001-00001-01-0000001451',
            IdentificadorDocumentoVentaReporte::valor($empresa, '00100001010000001451', 99)
        );
        self::assertSame('99', IdentificadorDocumentoVentaReporte::valor($empresa, null, 99));
    }

    public function test_otros_paises_mantienen_correlativo(): void
    {
        $empresa = new Empresa(['pais' => 'El Salvador', 'cod_pais' => 'SV']);

        self::assertSame('Correlativo', IdentificadorDocumentoVentaReporte::etiqueta($empresa));
        self::assertSame(
            '99',
            IdentificadorDocumentoVentaReporte::valor($empresa, '00100001010000001451', 99)
        );
    }
}

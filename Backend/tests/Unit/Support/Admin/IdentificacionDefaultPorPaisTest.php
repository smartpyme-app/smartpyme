<?php

namespace Tests\Unit\Support\Admin;

use App\Support\Admin\IdentificacionDefaultPorPais;
use PHPUnit\Framework\TestCase;

class IdentificacionDefaultPorPaisTest extends TestCase
{
    public function test_plantilla_cr_es_catalogo_hacienda(): void
    {
        $cfg = IdentificacionDefaultPorPais::plantilla('CR');

        $this->assertSame('01', $cfg['default_persona']);
        $this->assertSame('02', $cfg['default_empresa']);
        $this->assertSame('05', $cfg['default_extranjero']);
        $this->assertSame(['01', '02', '03', '04', '05', '06'], self::codigos($cfg['tipos']));
        $this->assertContains('06', self::codigosReceptor($cfg['tipos']));
        $this->assertNotContains('06', self::codigosEmisor($cfg['tipos']));
    }

    public function test_plantilla_sv_es_cat022(): void
    {
        $cfg = IdentificacionDefaultPorPais::plantilla('SV');

        $this->assertSame('13', $cfg['default_persona']);
        $this->assertSame('36', $cfg['default_empresa']);
        $this->assertSame('03', $cfg['default_extranjero']);
        $this->assertSame(['13', '36', '03', '02', '37'], self::codigos($cfg['tipos']));
    }

    public function test_plantilla_hn_dni_rtn_pasaporte(): void
    {
        $cfg = IdentificacionDefaultPorPais::plantilla('HN');

        $this->assertSame('dni', $cfg['default_persona']);
        $this->assertSame('rtn', $cfg['default_empresa']);
        $this->assertSame('pasaporte', $cfg['default_extranjero']);
        $this->assertSame(['dni', 'rtn', 'pasaporte'], self::codigos($cfg['tipos']));
    }

    public function test_default_para_tipo_cliente(): void
    {
        $this->assertSame('01', IdentificacionDefaultPorPais::defaultParaTipo('CR', 'Persona'));
        $this->assertSame('02', IdentificacionDefaultPorPais::defaultParaTipo('CR', 'Empresa'));
        $this->assertSame('05', IdentificacionDefaultPorPais::defaultParaTipo('CR', 'Extranjero'));
        $this->assertSame('13', IdentificacionDefaultPorPais::defaultParaTipo('SV', 'Persona'));
        $this->assertSame('rtn', IdentificacionDefaultPorPais::defaultParaTipo('HN', 'Empresa'));
    }

    /** @param list<array{codigo: string, uso?: list<string>}> $tipos */
    private static function codigos(array $tipos): array
    {
        return array_values(array_map(static fn (array $t) => $t['codigo'], $tipos));
    }

    /** @param list<array{codigo: string, uso?: list<string>}> $tipos */
    private static function codigosReceptor(array $tipos): array
    {
        return array_values(array_map(
            static fn (array $t) => $t['codigo'],
            array_filter($tipos, static fn (array $t) => in_array('receptor', $t['uso'] ?? [], true))
        ));
    }

    /** @param list<array{codigo: string, uso?: list<string>}> $tipos */
    private static function codigosEmisor(array $tipos): array
    {
        return array_values(array_map(
            static fn (array $t) => $t['codigo'],
            array_filter($tipos, static fn (array $t) => in_array('emisor', $t['uso'] ?? [], true))
        ));
    }
}

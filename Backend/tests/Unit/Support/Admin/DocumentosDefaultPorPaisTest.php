<?php

namespace Tests\Unit\Support\Admin;

use App\Models\Admin\Empresa;
use App\Support\Admin\DocumentosDefaultPorPais;
use PHPUnit\Framework\TestCase;

/**
 * Check mínimo: plantillas SV/CR/HN y seed de alta empresa.
 */
class DocumentosDefaultPorPaisTest extends TestCase
{
    public function test_plantillas_sv_cr_hn(): void
    {
        $sv = DocumentosDefaultPorPais::plantilla('SV');
        $cr = DocumentosDefaultPorPais::plantilla('CR');
        $hn = DocumentosDefaultPorPais::plantilla('HN');

        $this->assertNotEquals($sv['nombres'], $cr['nombres']);
        $this->assertNotEquals($sv['nombres'], $hn['nombres']);
        $this->assertContains(DocumentosDefaultPorPais::CR_FACTURA, $cr['nombres']);
        $this->assertContains('Crédito fiscal', $sv['nombres']);
        $this->assertContains('Factura sin RTN', $hn['nombres']);
        $this->assertContains('Factura con RTN', $hn['nombres']);
        $this->assertNotContains('Crédito fiscal', $hn['nombres']);
        $this->assertEmpty(array_diff($sv['seed'], $sv['nombres']));
        $this->assertEmpty(array_diff($cr['seed'], $cr['nombres']));
        $this->assertEmpty(array_diff($hn['seed'], $hn['nombres']));
    }

    public function test_defaults_honduras_sin_credito_fiscal(): void
    {
        $empresa = new Empresa();
        $empresa->pais = 'Honduras';
        $empresa->cod_pais = 'HN';

        $nombres = DocumentosDefaultPorPais::nombres($empresa);

        $this->assertSame(
            ['Ticket', 'Factura sin RTN', 'Cotización', 'Orden de compra'],
            $nombres
        );
        $this->assertNotContains('Crédito fiscal', $nombres);
    }

    public function test_defaults_el_salvador_incluye_credito_fiscal(): void
    {
        $empresa = new Empresa();
        $empresa->pais = 'El Salvador';
        $empresa->cod_pais = 'SV';

        $nombres = DocumentosDefaultPorPais::nombres($empresa);

        $this->assertContains('Crédito fiscal', $nombres);
        $this->assertContains('Factura', $nombres);
    }

    public function test_defaults_costa_rica_electronicos(): void
    {
        $empresa = new Empresa();
        $empresa->pais = 'Costa Rica';
        $empresa->cod_pais = 'CR';

        $nombres = DocumentosDefaultPorPais::nombres($empresa);

        $this->assertContains(DocumentosDefaultPorPais::CR_TIQUETE, $nombres);
        $this->assertContains(DocumentosDefaultPorPais::CR_FACTURA, $nombres);
        $this->assertNotContains('Crédito fiscal', $nombres);
    }
}

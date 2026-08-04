<?php

namespace Tests\Unit\Support\Admin;

use App\Models\Admin\Empresa;
use App\Support\Admin\DocumentosDefaultPorPais;
use Tests\TestCase;

final class DocumentosDefaultPorPaisTest extends TestCase
{
    public function test_defaults_honduras_sin_credito_fiscal(): void
    {
        $empresa = new Empresa();
        $empresa->pais = 'Honduras';
        $empresa->cod_pais = 'HN';

        $nombres = DocumentosDefaultPorPais::nombres($empresa);

        $this->assertSame(
            ['Ticket', 'Factura', 'Cotización', 'Orden de compra'],
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

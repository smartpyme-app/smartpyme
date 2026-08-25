<?php

namespace Tests\Unit\Services\CreditosClientes;

use App\Services\CreditosClientes\TipoDocumentoCredito;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class TipoDocumentoCreditoTest extends TestCase
{
    public function test_primera_cuota_permite_elegir_documento(): void
    {
        $this->assertFalse(TipoDocumentoCredito::documentoBloqueado(null));
    }

    public function test_siguientes_cuotas_quedan_fijas_al_primer_documento(): void
    {
        $this->assertTrue(TipoDocumentoCredito::documentoBloqueado(12));
        TipoDocumentoCredito::assertCompatible(12, 12);
        $this->expectException(InvalidArgumentException::class);
        TipoDocumentoCredito::assertCompatible(12, 99);
    }

    public function test_no_se_puede_vincular_si_ya_tiene_venta(): void
    {
        $this->assertFalse(TipoDocumentoCredito::puedeFacturar(5));
        $this->assertTrue(TipoDocumentoCredito::puedeFacturar(null));
    }
}

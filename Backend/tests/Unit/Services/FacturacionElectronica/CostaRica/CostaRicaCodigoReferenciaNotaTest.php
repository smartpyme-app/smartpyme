<?php

namespace Tests\Unit\Services\FacturacionElectronica\CostaRica;

use App\Services\FacturacionElectronica\CostaRica\CostaRicaFeEmitService;
use PHPUnit\Framework\TestCase;

final class CostaRicaCodigoReferenciaNotaTest extends TestCase
{
    public function test_nota_debito_corrige_monto_usa_02(): void
    {
        self::assertSame('02', CostaRicaFeEmitService::codigoReferenciaNotaDebito(false));
    }

    public function test_nota_debito_por_error_material_usa_14(): void
    {
        self::assertSame('14', CostaRicaFeEmitService::codigoReferenciaNotaDebito(true));
    }
}

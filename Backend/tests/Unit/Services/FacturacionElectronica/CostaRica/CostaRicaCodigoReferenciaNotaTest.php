<?php

namespace Tests\Unit\Services\FacturacionElectronica\CostaRica;

use App\Services\FacturacionElectronica\CostaRica\CostaRicaFeEmitService;
use PHPUnit\Framework\TestCase;

final class CostaRicaCodigoReferenciaNotaTest extends TestCase
{
    public function test_nota_debito_del_mismo_mes_usa_02(): void
    {
        $tz = new \DateTimeZone('America/Costa_Rica');
        $factura = new \DateTimeImmutable('2026-08-10 10:00:00', $tz);
        $nota = new \DateTimeImmutable('2026-08-28 10:00:00', $tz);

        $codigo = CostaRicaFeEmitService::codigoReferenciaNotaDebito($factura, $nota);

        self::assertSame('02', $codigo);
    }

    public function test_nota_debito_del_mes_siguiente_usa_14(): void
    {
        $tz = new \DateTimeZone('America/Costa_Rica');
        $factura = new \DateTimeImmutable('2026-08-31 22:00:00', $tz);
        $nota = new \DateTimeImmutable('2026-09-01 08:00:00', $tz);

        $codigo = CostaRicaFeEmitService::codigoReferenciaNotaDebito($factura, $nota);

        self::assertSame('14', $codigo);
    }
}

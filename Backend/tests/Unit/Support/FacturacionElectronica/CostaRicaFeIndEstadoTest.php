<?php

namespace Tests\Unit\Support\FacturacionElectronica;

use App\Services\FacturacionElectronica\CostaRica\CostaRicaFeEmitService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class CostaRicaFeIndEstadoTest extends TestCase
{
    public function test_normaliza_ind_estado_hacienda(): void
    {
        $method = new ReflectionMethod(CostaRicaFeEmitService::class, 'normalizarIndEstadoHacienda');
        $method->setAccessible(true);
        $service = (new \ReflectionClass(CostaRicaFeEmitService::class))->newInstanceWithoutConstructor();

        self::assertSame('aceptado', $method->invoke($service, ' Aceptado '));
        self::assertSame('', $method->invoke($service, null));
    }
}

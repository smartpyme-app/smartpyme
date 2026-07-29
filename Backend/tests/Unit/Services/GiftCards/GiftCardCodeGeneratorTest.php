<?php

namespace Tests\Unit\Services\GiftCards;

use App\Services\GiftCards\GiftCardCodeGenerator;
use PHPUnit\Framework\TestCase;

class GiftCardCodeGeneratorTest extends TestCase
{
    public function test_genera_codigo_con_prefijo_y_longitud(): void
    {
        $gen = new GiftCardCodeGenerator('GC', 12);
        $code = $gen->generate();
        $this->assertMatchesRegularExpression('/^GC[A-Z0-9]{10}$/', $code);
    }
}

<?php

namespace Tests\Unit\Support\Admin;

use App\Support\Admin\ImpuestosDefaultPorPais;
use PHPUnit\Framework\TestCase;

class ImpuestosDefaultPorPaisCheck extends TestCase
{
    public function test_hn_iva_distinto_de_sv(): void
    {
        $sv = ImpuestosDefaultPorPais::plantilla('SV');
        $hn = ImpuestosDefaultPorPais::plantilla('HN');

        $this->assertSame('USD', $sv['moneda']);
        $this->assertSame(13.0, $sv['iva']);
        $this->assertSame('HNL', $hn['moneda']);
        $this->assertSame(15.0, $hn['iva']);
        $this->assertSame(0.01, ImpuestosDefaultPorPais::plantilla('SV')['percepcion'] / 100);
    }
}

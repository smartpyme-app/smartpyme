<?php

namespace Tests\Unit\Services;

use App\Services\WooCommerceTallaNombreParser;
use PHPUnit\Framework\TestCase;

class WooCommerceTallaNombreParserTest extends TestCase
{
    public function test_extrae_talla_al_final(): void
    {
        $parser = new WooCommerceTallaNombreParser();
        $parsed = $parser->parse('ZW-3687P FUCSIA L');

        $this->assertSame('ZW-3687P FUCSIA', $parsed['base']);
        $this->assertSame('L', $parsed['talla']);
    }

    public function test_sin_talla_devuelve_nombre_completo(): void
    {
        $parser = new WooCommerceTallaNombreParser();
        $parsed = $parser->parse('PRODUCTO UNICO');

        $this->assertSame('PRODUCTO UNICO', $parsed['base']);
        $this->assertNull($parsed['talla']);
    }
}

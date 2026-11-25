<?php

namespace Tests\Unit;

use App\Models\HomeBanner;
use PHPUnit\Framework\TestCase;

class HomeBannerTest extends TestCase
{
    public function test_normalizar_paises_filtra_invalidos(): void
    {
        $this->assertSame(
            ['SV', 'GT'],
            HomeBanner::normalizarPaises(['sv', 'XX', 'GT', 'GT', ''])
        );
    }

    public function test_normalizar_paises_vacio_significa_todos(): void
    {
        $this->assertSame([], HomeBanner::normalizarPaises(null));
        $this->assertSame([], HomeBanner::normalizarPaises([]));
    }
}
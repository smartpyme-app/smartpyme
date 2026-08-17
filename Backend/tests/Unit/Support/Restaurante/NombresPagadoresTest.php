<?php

namespace Tests\Unit\Support\Restaurante;

use App\Support\Restaurante\NombresPagadores;
use PHPUnit\Framework\TestCase;

final class NombresPagadoresTest extends TestCase
{
    public function test_vacio_usa_persona_n(): void
    {
        $this->assertSame(['Persona 1', 'Persona 2'], NombresPagadores::normalizar(['', '  '], 2));
    }

    public function test_rellena_y_recorta(): void
    {
        $this->assertSame(['Ana', 'Persona 2'], NombresPagadores::normalizar(['Ana'], 2));
        $this->assertSame(['Ana'], NombresPagadores::normalizar(['Ana', 'Luis'], 1));
    }

    public function test_trim_y_tope_80(): void
    {
        $largo = str_repeat('á', 81);
        $out = NombresPagadores::normalizar(['  Ana  ', $largo], 2);
        $this->assertSame('Ana', $out[0]);
        $this->assertSame(80, mb_strlen($out[1]));
    }
}

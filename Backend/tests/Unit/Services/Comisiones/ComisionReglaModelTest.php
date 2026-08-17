<?php

namespace Tests\Unit\Services\Comisiones;

use App\Models\Comisiones\ComisionRegla;
use PHPUnit\Framework\TestCase;

class ComisionReglaModelTest extends TestCase
{
    public function test_constantes_tipo_alcance_momento(): void
    {
        $this->assertSame('por_categoria', ComisionRegla::TIPO_POR_CATEGORIA);
        $this->assertSame('por_volumen', ComisionRegla::TIPO_POR_VOLUMEN);
        $this->assertSame('por_margen', ComisionRegla::TIPO_POR_MARGEN);
        $this->assertSame('global', ComisionRegla::ALCANCE_GLOBAL);
        $this->assertSame('individual', ComisionRegla::ALCANCE_INDIVIDUAL);
        $this->assertSame('equipo', ComisionRegla::ALCANCE_EQUIPO);
        $this->assertSame('al_pagar', ComisionRegla::MOMENTO_AL_PAGAR);
        $this->assertSame('al_facturar', ComisionRegla::MOMENTO_AL_FACTURAR);
        $this->assertSame('por_abono', ComisionRegla::MOMENTO_POR_ABONO);
    }
}

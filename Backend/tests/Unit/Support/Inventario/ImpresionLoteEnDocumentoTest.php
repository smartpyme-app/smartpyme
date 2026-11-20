<?php

namespace Tests\Unit\Support\Inventario;

use App\Models\Admin\Empresa;
use App\Support\Inventario\ImpresionLoteEnDocumento;
use Mockery;
use Tests\TestCase;

class ImpresionLoteEnDocumentoTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_mostrar_lotes_solo_si_empresa_tiene_lotes_activo(): void
    {
        $off = Mockery::mock(Empresa::class);
        $off->shouldReceive('isLotesActivo')->andReturn(false);
        $this->assertFalse(ImpresionLoteEnDocumento::mostrarLotes($off));

        $on = Mockery::mock(Empresa::class);
        $on->shouldReceive('isLotesActivo')->andReturn(true);
        $this->assertTrue(ImpresionLoteEnDocumento::mostrarLotes($on));
    }
}

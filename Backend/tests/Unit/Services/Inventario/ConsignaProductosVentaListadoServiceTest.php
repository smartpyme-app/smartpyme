<?php

namespace Tests\Unit\Services\Inventario;

use App\Models\Admin\Empresa;
use App\Services\Inventario\ConsignaProductosVentaListadoService;
use Mockery;
use Tests\TestCase;

class ConsignaProductosVentaListadoServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_agrupar_por_lote_solo_con_ledger_y_lotes(): void
    {
        $service = new ConsignaProductosVentaListadoService();

        $empresa = Mockery::mock(Empresa::class);
        $empresa->shouldReceive('isModuloConsignacionesLedgerActivo')->andReturn(false);
        $empresa->shouldReceive('isLotesActivo')->andReturn(true);
        $this->assertFalse($service->agruparPorProductoYLote($empresa));

        $empresa2 = Mockery::mock(Empresa::class);
        $empresa2->shouldReceive('isModuloConsignacionesLedgerActivo')->andReturn(true);
        $empresa2->shouldReceive('isLotesActivo')->andReturn(false);
        $this->assertFalse($service->agruparPorProductoYLote($empresa2));

        $empresa3 = Mockery::mock(Empresa::class);
        $empresa3->shouldReceive('isModuloConsignacionesLedgerActivo')->andReturn(true);
        $empresa3->shouldReceive('isLotesActivo')->andReturn(true);
        $this->assertTrue($service->agruparPorProductoYLote($empresa3));
    }
}

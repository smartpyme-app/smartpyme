<?php

namespace Tests\Unit\Services\Inventario;

use App\Constants\ConsignaMovimientoTipoConstants;
use App\Models\Admin\Empresa;
use App\Models\Inventario\ConsignaMovimiento;
use App\Services\Inventario\ConsignaCustodiaService;
use Mockery;
use Tests\TestCase;

class ConsignaCustodiaServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_incluir_lote_en_export_requiere_ledger_y_lotes(): void
    {
        $service = new ConsignaCustodiaService();
        $empresa = Mockery::mock(Empresa::class);
        $empresa->shouldReceive('isModuloConsignacionesLedgerActivo')->andReturn(true);
        $empresa->shouldReceive('isLotesActivo')->andReturn(true);
        $this->assertTrue($service->incluirLoteEnExport($empresa));
    }

    public function test_delta_entrega_usa_valor_absoluto(): void
    {
        $service = new ConsignaCustodiaService();
        $movimiento = new ConsignaMovimiento([
            'tipo' => ConsignaMovimientoTipoConstants::ENTREGA_CONSIGNA_PROPIO,
            'cantidad' => -5,
            'id_cliente' => 1,
        ]);

        $this->assertSame(5.0, $service->deltaDesdeMovimiento($movimiento));
    }

    public function test_delta_liquidacion_cliente_resta(): void
    {
        $service = new ConsignaCustodiaService();
        $movimiento = new ConsignaMovimiento([
            'tipo' => ConsignaMovimientoTipoConstants::LIQUIDACION_CLIENTE,
            'cantidad' => -2,
            'id_cliente' => 1,
        ]);

        $this->assertSame(-2.0, $service->deltaDesdeMovimiento($movimiento));
    }
}

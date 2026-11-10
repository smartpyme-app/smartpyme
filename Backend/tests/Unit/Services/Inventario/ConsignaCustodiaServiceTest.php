<?php

namespace Tests\Unit\Services\Inventario;

use App\Constants\ConsignaMovimientoTipoConstants;
use App\Models\Inventario\ConsignaMovimiento;
use App\Services\Inventario\ConsignaCustodiaService;
use Tests\TestCase;

class ConsignaCustodiaServiceTest extends TestCase
{
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
